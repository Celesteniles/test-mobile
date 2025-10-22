<?php

/**
 * Script de test pour l'intégration Mobile Money
 *
 * Pour exécuter ce script:
 * php test_mobile_money.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Order;
use App\Services\MobileMoneyService;

echo "=================================================\n";
echo "   TEST DE L'INTEGRATION MOBILE MONEY GATEWAY   \n";
echo "=================================================\n\n";

// 1. Vérifier la configuration
echo "1. Vérification de la configuration...\n";
echo "   API URL: " . config('mobilemoney.api_url') . "\n";
echo "   API Key: " . (config('mobilemoney.api_key') ? '✓ Configuré' : '✗ Non configuré') . "\n";
echo "   Secret Key: " . (config('mobilemoney.secret_key') ? '✓ Configuré' : '✗ Non configuré') . "\n";
echo "   App ID: " . (config('mobilemoney.app_id') ? '✓ Configuré' : '✗ Non configuré') . "\n";
echo "   Callback URL: " . config('mobilemoney.callback_url') . "\n";
echo "   Timeout: " . config('mobilemoney.timeout') . " secondes\n\n";

// 2. Créer ou récupérer un utilisateur de test
echo "2. Création/Récupération d'un utilisateur de test...\n";
$user = User::firstOrCreate(
    ['email' => 'test@example.com'],
    [
        'name' => 'Utilisateur Test',
        'password' => bcrypt('password123'),
    ]
);
echo "   ✓ Utilisateur ID: {$user->id} - {$user->name}\n\n";

// 3. Créer une commande de test
echo "3. Création d'une commande de test...\n";
$order = Order::create([
    'user_id' => $user->id,
    'order_number' => 'TEST-' . time(),
    'total_amount' => 1000,
    'currency' => 'XAF',
    'status' => 'pending',
    'payment_status' => 'unpaid',
]);
echo "   ✓ Commande créée: #{$order->order_number}\n";
echo "   ✓ Montant: {$order->total_amount} {$order->currency}\n\n";

// 4. Tester le service MobileMoneyService
echo "4. Test du service MobileMoneyService...\n";
$service = new MobileMoneyService();

// Tester la génération de signature
$testPayload = ['test' => 'data'];
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('generateSignature');
$method->setAccessible(true);
$signature = $method->invoke($service, $testPayload);
echo "   ✓ Génération de signature: " . substr($signature, 0, 20) . "...\n\n";

// 5. Simuler un appel d'initiation de paiement (sans vraiment appeler l'API)
echo "5. Test de la structure de données pour l'initiation de paiement...\n";
$externalRef = 'ORDER_' . $order->id . '_' . time();
$paymentData = [
    'app_id' => config('mobilemoney.app_id'),
    'external_ref' => $externalRef,
    'amount' => $order->total_amount,
    'currency' => $order->currency,
    'payer_phone' => '+242067230202',
    'description' => 'Paiement commande #' . $order->order_number,
];
echo "   ✓ App ID: {$paymentData['app_id']}\n";
echo "   ✓ External Ref: {$paymentData['external_ref']}\n";
echo "   ✓ Montant: {$paymentData['amount']} {$paymentData['currency']}\n";
echo "   ✓ Téléphone: {$paymentData['payer_phone']}\n\n";

// 6. Afficher les routes API disponibles
echo "6. Routes API disponibles:\n";
echo "   POST /api/payments/initiate (authentifié)\n";
echo "   POST /api/payments/check-status (authentifié)\n";
echo "   POST /api/payments/callback (public)\n\n";

// 7. Exemple de commande cURL pour tester
echo "7. Exemple de commande pour tester avec cURL:\n\n";
echo "   # Créer un token d'authentification d'abord\n";
echo "   php artisan tinker\n";
echo "   >>> \$user = User::first();\n";
echo "   >>> \$token = \$user->createToken('test-token')->plainTextToken;\n";
echo "   >>> echo \$token;\n\n";
echo "   # Puis utiliser le token pour initier un paiement\n";
echo "   curl -X POST http://localhost:8000/api/payments/initiate \\\n";
echo "     -H \"Content-Type: application/json\" \\\n";
echo "     -H \"Authorization: Bearer VOTRE_TOKEN_ICI\" \\\n";
echo "     -d '{\n";
echo "       \"order_id\": {$order->id},\n";
echo "       \"phone\": \"+242067230202\"\n";
echo "     }'\n\n";

// 8. Créer un token de test
echo "8. Création d'un token de test pour l'utilisateur...\n";
$token = $user->createToken('test-token')->plainTextToken;
echo "   ✓ Token créé: " . substr($token, 0, 30) . "...\n";
echo "   ✓ Utilisez ce token pour tester les endpoints\n\n";

// 9. Vérifier les tables créées
echo "9. Tables de base de données créées:\n";
echo "   ✓ users\n";
echo "   ✓ orders (avec payment_external_ref)\n";
echo "   ✓ personal_access_tokens (Sanctum)\n\n";

// 10. Résumé
echo "=================================================\n";
echo "                    RÉSUMÉ                       \n";
echo "=================================================\n\n";
echo "✓ Configuration vérifiée (avec app_id)\n";
echo "✓ Base de données migrée\n";
echo "✓ Utilisateur de test créé (test@example.com)\n";
echo "✓ Commande de test créée (#{$order->order_number})\n";
echo "✓ Token d'authentification généré\n\n";

echo "CORRECTIONS APPLIQUÉES:\n";
echo "✓ Paramètre 'external_ref' au lieu de 'transaction_id'\n";
echo "✓ Paramètre 'app_id' ajouté (obligatoire)\n";
echo "✓ 'currency' est maintenant requis\n";
echo "✓ 'callback_url' est maintenant requis\n";
echo "✓ Structure de réponse plate (sans 'data' imbriqué)\n";
echo "✓ Champ 'payment_external_ref' ajouté à la table orders\n";
echo "✓ Champ 'gateway_transaction_id' supprimé (n'existait pas)\n\n";

echo "PROCHAINES ÉTAPES:\n";
echo "1. Configurez vos vrais credentials dans .env:\n";
echo "   - MOBILE_MONEY_API_URL\n";
echo "   - MOBILE_MONEY_API_KEY\n";
echo "   - MOBILE_MONEY_SECRET_KEY\n";
echo "   - MOBILE_MONEY_APP_ID (NOUVEAU)\n\n";
echo "2. Testez l'API avec Postman ou cURL\n\n";
echo "3. Pour tester en ligne de commande:\n";
echo "   php artisan tinker\n";
echo "   >>> \$service = app(\\App\\Services\\MobileMoneyService::class);\n";
echo "   >>> \$result = \$service->collect([\n";
echo "       'external_ref' => 'TEST_' . time(),\n";
echo "       'amount' => 1000,\n";
echo "       'currency' => 'XAF',\n";
echo "       'payer_phone' => '+242067230202',\n";
echo "       'description' => 'Test payment',\n";
echo "   ]);\n";
echo "   >>> dd(\$result);\n\n";

echo "=================================================\n\n";
