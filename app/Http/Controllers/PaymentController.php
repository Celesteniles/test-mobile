<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected MobileMoneyService $mobileMoneyService;

    public function __construct(MobileMoneyService $mobileMoneyService)
    {
        $this->mobileMoneyService = $mobileMoneyService;
    }

    /**
     * Initier un paiement pour une commande
     */
    public function initiatePayment(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'phone' => 'required|string|regex:/^\+?[0-9]{10,15}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer la commande
        $order = Order::findOrFail($request->order_id);

        // Vérifier que la commande n'est pas déjà payée
        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande a déjà été payée'
            ], 400);
        }

        // Vérifier qu'il n'y a pas déjà un paiement en cours
        if ($order->payment_status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Un paiement est déjà en cours pour cette commande'
            ], 400);
        }

        // Générer la référence externe
        $externalRef = 'ORDER_' . $order->id . '_' . time();

        // Initier le paiement via le service
        $result = $this->mobileMoneyService->collect([
            'external_ref' => $externalRef,
            'amount' => $order->total_amount,
            'currency' => $order->currency ?? 'XAF',
            'payer_phone' => $request->phone,
            'description' => 'Paiement commande #' . $order->order_number,
        ]);

        if ($result['success'] && $result['data']) {
            // Mettre à jour la commande
            $order->update([
                'payment_status' => 'pending',
                'payment_transaction_id' => $result['data']['transaction_id'] ?? null,
                'payment_external_ref' => $result['data']['external_ref'] ?? $externalRef,
                'payment_phone' => $request->phone,
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['data']['message'] ?? 'Paiement initié avec succès. Veuillez confirmer sur votre téléphone.',
                'data' => [
                    'order_id' => $order->id,
                    'transaction_id' => $result['data']['transaction_id'] ?? null,
                    'external_ref' => $result['data']['external_ref'] ?? $externalRef,
                    'status' => $result['data']['status'] ?? 'PENDING',
                    'amount' => $result['data']['amount'] ?? $order->total_amount,
                    'operator' => $result['data']['operator'] ?? null,
                    'payment_url' => $result['data']['payment_url'] ?? null,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Échec de l\'initiation du paiement',
            'errors' => $result['errors'] ?? null,
        ], $result['status_code'] ?? 500);
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::findOrFail($request->order_id);

        if (!$order->payment_transaction_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun paiement n\'a été initié pour cette commande'
            ], 400);
        }

        // Vérifier le statut via le service
        $result = $this->mobileMoneyService->verify($order->payment_transaction_id);

        if ($result['success'] && $result['data']) {
            $status = $result['data']['status'];

            // Mettre à jour le statut de la commande
            if ($status === 'SUCCESS' && $order->payment_status !== 'paid') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'paid_at' => now(),
                ]);
            } elseif (in_array($status, ['FAILED', 'EXPIRED'])) {
                $order->update([
                    'payment_status' => 'failed',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status,
                    'transaction_status' => $status,
                    'amount' => $result['data']['amount'],
                    'operator' => $result['data']['operator'] ?? null,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Échec de la vérification',
        ], $result['status_code'] ?? 500);
    }

    /**
     * Recevoir les callbacks du gateway
     */
    public function handleCallback(Request $request)
    {
        // Logger tous les callbacks reçus
        Log::info('Payment Callback Received', [
            'data' => $request->all(),
            'ip' => $request->ip(),
        ]);

        // Extraire les données du callback
        $transactionId = $request->input('transaction_id');
        $externalRef = $request->input('external_ref');
        $status = $request->input('status');

        // Trouver la commande correspondante (par external_ref ou transaction_id)
        $order = Order::where('payment_external_ref', $externalRef)
            ->orWhere('payment_transaction_id', $transactionId)
            ->first();

        if (!$order) {
            Log::warning('Callback - Order not found', [
                'transaction_id' => $transactionId,
                'external_ref' => $externalRef
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Mettre à jour la commande selon le statut
        if ($status === 'SUCCESS' && $order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
                'paid_at' => now(),
                'payment_transaction_id' => $transactionId,
            ]);

            Log::info('Callback - Payment Success', [
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'external_ref' => $externalRef,
            ]);

            // Déclencher des événements ou notifications
            // event(new OrderPaid($order));
            // Notification::send($order->user, new PaymentSuccess($order));

        } elseif (in_array($status, ['FAILED', 'EXPIRED'])) {
            $order->update([
                'payment_status' => 'failed',
            ]);

            Log::info('Callback - Payment Failed', [
                'order_id' => $order->id,
                'status' => $status,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Callback received and processed'
        ]);
    }
}
