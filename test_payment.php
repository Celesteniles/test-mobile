#!/usr/bin/env php
<?php

/**
 * Script de Test Rapide - Mobile Money Payment
 *
 * Exécution : php test_payment.php [montant]
 * Exemple : php test_payment.php 500
 *
 * Si aucun montant n'est fourni, un montant aléatoire entre 100 et 5000 XAF sera utilisé.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Order;
use App\Services\MobileMoneyService;

// Fonction pour afficher un message en couleur
function colorize($text, $color = 'green') {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'cyan' => "\033[0;36m",
        'white' => "\033[1;37m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? $colors['white']) . $text . $colors['reset'];
}

// Bannière
echo "\n";
echo colorize("╔═══════════════════════════════════════════════════════════════╗", 'cyan') . "\n";
echo colorize("║          TEST RAPIDE - MOBILE MONEY PAYMENT                  ║", 'cyan') . "\n";
echo colorize("╚═══════════════════════════════════════════════════════════════╝", 'cyan') . "\n\n";

// Récupérer le montant depuis les arguments ou générer aléatoirement
$amount = isset($argv[1]) ? (int)$argv[1] : rand(100, 5000);

if ($amount < 100) {
    echo colorize("❌ Erreur : Le montant minimum est de 100 XAF\n", 'red');
    exit(1);
}

echo colorize("💰 Montant du test : $amount XAF\n\n", 'yellow');

// 1. Vérifier la configuration
echo colorize("🔧 Vérification de la configuration...\n", 'blue');
$config = config('mobilemoney');

if (!$config['api_key'] || !$config['secret_key'] || !$config['app_id']) {
    echo colorize("❌ Configuration incomplète ! Vérifiez votre fichier .env\n", 'red');
    exit(1);
}

echo colorize("  ✓ API URL    : " . $config['api_url'] . "\n", 'green');
echo colorize("  ✓ API Key    : Configuré\n", 'green');
echo colorize("  ✓ Secret Key : Configuré\n", 'green');
echo colorize("  ✓ App ID     : Configuré\n\n", 'green');

// 2. Créer/récupérer l'utilisateur
echo colorize("👤 Préparation de l'utilisateur de test...\n", 'blue');
$user = User::firstOrCreate(
    ['email' => 'test@example.com'],
    ['name' => 'Test User', 'password' => bcrypt('password123')]
);
echo colorize("  ✓ User ID : {$user->id} ({$user->email})\n\n", 'green');

// 3. Créer la commande
echo colorize("📦 Création de la commande...\n", 'blue');
$orderNumber = 'TEST' . $amount . '-' . time();
$order = Order::create([
    'user_id' => $user->id,
    'order_number' => $orderNumber,
    'total_amount' => $amount,
    'currency' => 'XAF',
    'status' => 'pending',
    'payment_status' => 'unpaid',
]);
echo colorize("  ✓ Commande : #{$order->order_number}\n", 'green');
echo colorize("  ✓ Montant  : {$order->total_amount} XAF\n\n", 'green');

// 4. Initier le paiement
echo colorize("🚀 Initiation du paiement...\n", 'blue');
$service = app(MobileMoneyService::class);
$externalRef = 'ORDER_' . $order->id . '_' . time();

echo colorize("  → External Ref : $externalRef\n", 'white');
echo colorize("  → Téléphone    : +242067230202\n", 'white');
echo colorize("  → Appel API en cours...\n\n", 'white');

$startTime = microtime(true);

try {
    $result = $service->collect([
        'external_ref' => $externalRef,
        'amount' => $amount,
        'currency' => 'XAF',
        'payer_phone' => '242067230202',
        'description' => "Test paiement $amount XAF - Commande #$orderNumber",
    ]);

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);

    echo colorize("⏱️  Temps de réponse : $duration ms\n\n", 'cyan');

    // 5. Afficher le résultat
    if ($result['success']) {
        $data = $result['data'];

        echo colorize("╔═══════════════════════════════════════════════════════════════╗\n", 'green');
        echo colorize("║                    ✅ SUCCÈS - PAIEMENT INITIÉ               ║\n", 'green');
        echo colorize("╚═══════════════════════════════════════════════════════════════╝\n\n", 'green');

        echo colorize("📋 Détails de la transaction :\n", 'cyan');
        echo colorize("  🆔 Transaction ID  : " . ($data['transaction_id'] ?? 'N/A') . "\n", 'white');
        echo colorize("  🔗 External Ref    : " . ($data['external_ref'] ?? 'N/A') . "\n", 'white');
        echo colorize("  📊 Status          : " . ($data['status'] ?? 'N/A') . "\n", 'white');
        echo colorize("  📱 Opérateur       : " . ($data['operator'] ?? 'N/A') . "\n", 'white');
        echo colorize("  📞 Téléphone       : " . ($data['payer_phone'] ?? 'N/A') . "\n", 'white');
        echo colorize("  🌐 Payment URL     : " . ($data['payment_url'] ?? 'N/A') . "\n\n", 'white');

        // Mettre à jour la commande
        $order->update([
            'payment_status' => 'pending',
            'payment_transaction_id' => $data['transaction_id'] ?? null,
            'payment_external_ref' => $data['external_ref'] ?? $externalRef,
            'payment_phone' => '+242067230202',
        ]);

        echo colorize("✓ Base de données mise à jour\n\n", 'green');

        // Statistiques
        $totalTransactions = Order::whereNotNull('payment_transaction_id')->count();
        $totalAmount = Order::whereNotNull('payment_transaction_id')->sum('total_amount');

        echo colorize("📊 Statistiques globales :\n", 'cyan');
        echo colorize("  Total transactions : $totalTransactions\n", 'white');
        echo colorize("  Montant total      : " . number_format($totalAmount, 0, ',', ' ') . " XAF\n\n", 'white');

        echo colorize("🎉 Le paiement a été initié avec succès !\n", 'green');
        echo colorize("📱 Le client doit maintenant confirmer sur son téléphone.\n\n", 'yellow');

        exit(0);

    } else {
        echo colorize("╔═══════════════════════════════════════════════════════════════╗\n", 'red');
        echo colorize("║                    ❌ ÉCHEC - ERREUR API                     ║\n", 'red');
        echo colorize("╚═══════════════════════════════════════════════════════════════╝\n\n", 'red');

        echo colorize("Message : " . $result['message'] . "\n", 'red');
        echo colorize("Status Code : " . $result['status_code'] . "\n\n", 'red');

        if (isset($result['errors'])) {
            echo colorize("Erreurs :\n", 'red');
            print_r($result['errors']);
        }

        exit(1);
    }

} catch (\Exception $e) {
    echo colorize("╔═══════════════════════════════════════════════════════════════╗\n", 'red');
    echo colorize("║                    ❌ EXCEPTION CAPTURÉE                     ║\n", 'red');
    echo colorize("╚═══════════════════════════════════════════════════════════════╝\n\n", 'red');

    echo colorize("Type    : " . get_class($e) . "\n", 'red');
    echo colorize("Message : " . $e->getMessage() . "\n", 'red');
    echo colorize("Fichier : " . $e->getFile() . ":" . $e->getLine() . "\n\n", 'red');

    exit(1);
}
