# Guide de Test avec Tinker - Mobile Money Gateway

Ce guide vous permet de tester l'intégration Mobile Money directement depuis la ligne de commande avec Artisan Tinker.

---

## Lancer Tinker

```bash
php artisan tinker
```

---

## Test 1 : Vérifier la Configuration

```php
// Vérifier tous les paramètres de configuration
config('mobilemoney');

// Vérifier individuellement
config('mobilemoney.api_url');
config('mobilemoney.api_key');
config('mobilemoney.secret_key');
config('mobilemoney.app_id');
config('mobilemoney.callback_url');
```

**Résultat attendu :**
Tous les paramètres doivent être configurés et non-null.

---

## Test 2 : Créer un Utilisateur de Test

```php
// Créer ou récupérer l'utilisateur
$user = \App\Models\User::firstOrCreate(
    ['email' => 'test@example.com'],
    [
        'name' => 'Test User',
        'password' => bcrypt('password123')
    ]
);

echo "Utilisateur créé : ID {$user->id}, Email: {$user->email}";
```

---

## Test 3 : Créer une Commande de Test

```php
// Créer une commande
$order = \App\Models\Order::create([
    'user_id' => $user->id,
    'order_number' => 'TEST-' . time(),
    'total_amount' => 5000,
    'currency' => 'XAF',
    'status' => 'pending',
    'payment_status' => 'unpaid',
]);

echo "Commande créée : #{$order->order_number}, Montant: {$order->total_amount} {$order->currency}";
```

---

## Test 4 : Tester la Génération de Signature

```php
// Instancier le service
$service = app(\App\Services\MobileMoneyService::class);

// Utiliser Reflection pour tester la méthode protected
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('generateSignature');
$method->setAccessible(true);

// Test avec un payload
$testPayload = [
    'app_id' => config('mobilemoney.app_id'),
    'external_ref' => 'TEST_123',
    'amount' => 1000,
    'currency' => 'XAF',
];

$signature = $method->invoke($service, $testPayload);

echo "Signature générée : " . $signature;
```

---

## Test 5 : Initier un Paiement (Appel API Réel)

⚠️ **ATTENTION** : Ceci fait un appel réel à l'API. Assurez-vous que vos credentials sont corrects.

```php
// Instancier le service
$service = app(\App\Services\MobileMoneyService::class);

// Préparer les données
$externalRef = 'TINKER_TEST_' . time();

// Initier le paiement
$result = $service->collect([
    'external_ref' => $externalRef,
    'amount' => 1000,
    'currency' => 'XAF',
    'payer_phone' => '+242067230202',  // Remplacez par un vrai numéro
    'description' => 'Test de paiement depuis Tinker',
]);

// Afficher le résultat
dd($result);
```

**Résultat attendu si succès :**
```php
[
    "success" => true,
    "data" => [
        "error" => false,
        "message" => "Transaction initiated successfully",
        "status" => "PENDING",
        "transaction_id" => "TXN_abc123...",
        "external_ref" => "TINKER_TEST_1234567890",
        "payment_url" => "https://gateway.example.com/checkout/...",
        "operator" => "MTN",
        "payer_phone" => "+242067230202",
    ],
    "message" => "Transaction initiated successfully",
    "status_code" => 201,
    "errors" => null,
]
```

---

## Test 6 : Vérifier le Statut d'une Transaction

```php
// Utiliser le transaction_id obtenu précédemment
$transactionId = 'TXN_abc123...'; // Remplacez par le vrai transaction_id

$service = app(\App\Services\MobileMoneyService::class);

$result = $service->verify($transactionId);

dd($result);
```

**Résultat attendu :**
```php
[
    "success" => true,
    "data" => [
        "error" => false,
        "transaction_id" => "TXN_abc123...",
        "external_ref" => "TINKER_TEST_1234567890",
        "status" => "SUCCESS",  // ou "PENDING", "FAILED", "EXPIRED"
        "amount" => 1000.0,
        "currency" => "XAF",
        "payer_phone" => "+242067230202",
        "operator" => "MTN",
        "description" => "Test de paiement depuis Tinker",
        "response_code" => "00",
        "transaction_time" => "2024-10-22T10:31:45+00:00",
        "created_at" => "2024-10-22T10:30:00+00:00",
    ],
    "message" => "Transaction retrieved",
    "status_code" => 200,
]
```

---

## Test 7 : Test Complet avec Mise à Jour de Commande

```php
// 1. Créer une commande
$order = \App\Models\Order::create([
    'user_id' => 1,  // Utilisez un ID valide
    'order_number' => 'ORDER-' . time(),
    'total_amount' => 2500,
    'currency' => 'XAF',
    'status' => 'pending',
    'payment_status' => 'unpaid',
]);

echo "Commande créée : #{$order->order_number}\n";

// 2. Initier le paiement
$service = app(\App\Services\MobileMoneyService::class);
$externalRef = 'ORDER_' . $order->id . '_' . time();

$result = $service->collect([
    'external_ref' => $externalRef,
    'amount' => $order->total_amount,
    'currency' => $order->currency,
    'payer_phone' => '+242067230202',
    'description' => 'Paiement commande #' . $order->order_number,
]);

echo "Paiement initié : " . ($result['success'] ? 'Succès' : 'Échec') . "\n";

// 3. Mettre à jour la commande
if ($result['success']) {
    $order->update([
        'payment_status' => 'pending',
        'payment_transaction_id' => $result['data']['transaction_id'] ?? null,
        'payment_external_ref' => $result['data']['external_ref'] ?? $externalRef,
        'payment_phone' => '+242067230202',
    ]);

    echo "Commande mise à jour\n";
    echo "Transaction ID: " . $order->payment_transaction_id . "\n";
    echo "External Ref: " . $order->payment_external_ref . "\n";
}

// 4. Afficher les détails complets
$order->fresh();
print_r($order->toArray());
```

---

## Test 8 : Simuler un Callback

```php
// Préparer les données de callback
$callbackData = [
    'transaction_id' => 'TXN_abc123...',  // Remplacez par un vrai ID
    'external_ref' => 'ORDER_1_1234567890',
    'status' => 'SUCCESS',
    'amount' => 2500,
    'currency' => 'XAF',
    'operator' => 'MTN',
    'payer_phone' => '+242067230202',
    'response_code' => '00',
    'transaction_time' => now()->toIso8601String(),
];

// Trouver la commande
$order = \App\Models\Order::where('payment_external_ref', $callbackData['external_ref'])->first();

if ($order) {
    echo "Commande trouvée : #{$order->order_number}\n";
    echo "Statut actuel : {$order->payment_status}\n";

    // Mettre à jour selon le callback
    if ($callbackData['status'] === 'SUCCESS') {
        $order->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => now(),
            'payment_transaction_id' => $callbackData['transaction_id'],
        ]);
        echo "✓ Paiement confirmé avec succès\n";
    }

    $order->fresh();
    echo "Nouveau statut : {$order->payment_status}\n";
} else {
    echo "❌ Commande non trouvée\n";
}
```

---

## Test 9 : Lister Toutes les Commandes avec Paiement

```php
// Toutes les commandes
$orders = \App\Models\Order::with('user')->get();

echo "Total des commandes : " . $orders->count() . "\n\n";

// Afficher chaque commande
foreach ($orders as $order) {
    echo "Commande #{$order->order_number}\n";
    echo "  Montant: {$order->total_amount} {$order->currency}\n";
    echo "  Statut: {$order->status}\n";
    echo "  Paiement: {$order->payment_status}\n";
    echo "  External Ref: {$order->payment_external_ref}\n";
    echo "  Transaction ID: {$order->payment_transaction_id}\n";
    echo "  Téléphone: {$order->payment_phone}\n";
    echo "  Payé le: " . ($order->paid_at ? $order->paid_at->format('Y-m-d H:i:s') : 'Non payé') . "\n";
    echo "  ---\n";
}
```

---

## Test 10 : Nettoyer les Données de Test

```php
// ⚠️ ATTENTION : Ceci supprime des données !

// Supprimer les commandes de test
\App\Models\Order::where('order_number', 'LIKE', 'TEST-%')
    ->orWhere('order_number', 'LIKE', 'ORDER-%')
    ->delete();

echo "Commandes de test supprimées\n";

// Supprimer l'utilisateur de test (optionnel)
\App\Models\User::where('email', 'test@example.com')->delete();

echo "Utilisateur de test supprimé\n";

// Vérifier
echo "Commandes restantes : " . \App\Models\Order::count() . "\n";
echo "Utilisateurs restants : " . \App\Models\User::count() . "\n";
```

---

## Gestion des Erreurs Communes

### Erreur : "Invalid signature"

```php
// Vérifier que la secret_key est correcte
echo "Secret Key configurée : " . (config('mobilemoney.secret_key') ? 'Oui' : 'Non');

// Tester la signature manuellement
$payload = ['test' => 'data'];
$signature = hash_hmac('sha256', json_encode($payload), config('mobilemoney.secret_key'));
echo "Signature de test : " . $signature;
```

### Erreur : "App ID is required"

```php
// Vérifier l'app_id
echo "App ID : " . config('mobilemoney.app_id');

// Si null, mettre à jour .env puis recharger
Artisan::call('config:clear');
echo config('mobilemoney.app_id');
```

### Erreur de connexion

```php
// Tester la connectivité
$service = app(\App\Services\MobileMoneyService::class);

try {
    $result = $service->verify('TEST_NON_EXISTENT');
    echo "API accessible : Oui\n";
} catch (\Exception $e) {
    echo "Erreur de connexion : " . $e->getMessage() . "\n";
}
```

---

## Raccourcis Utiles

```php
// Recharger la configuration
Artisan::call('config:clear');

// Voir les logs en temps réel
tail -f storage/logs/laravel.log

// Quitter Tinker
exit
```

---

## Script Complet de Test

Copiez-collez ce script complet dans Tinker pour un test de bout en bout :

```php
echo "=== DÉBUT DU TEST MOBILE MONEY ===\n\n";

// 1. Vérifier la configuration
echo "1. Configuration...\n";
$config = config('mobilemoney');
echo "  API URL: {$config['api_url']}\n";
echo "  API Key: " . (isset($config['api_key']) ? '✓' : '✗') . "\n";
echo "  App ID: " . (isset($config['app_id']) ? '✓' : '✗') . "\n\n";

// 2. Créer un utilisateur
echo "2. Création utilisateur...\n";
$user = \App\Models\User::firstOrCreate(
    ['email' => 'test@example.com'],
    ['name' => 'Test User', 'password' => bcrypt('password123')]
);
echo "  ✓ Utilisateur ID: {$user->id}\n\n";

// 3. Créer une commande
echo "3. Création commande...\n";
$order = \App\Models\Order::create([
    'user_id' => $user->id,
    'order_number' => 'TINKER-' . time(),
    'total_amount' => 1000,
    'currency' => 'XAF',
    'status' => 'pending',
    'payment_status' => 'unpaid',
]);
echo "  ✓ Commande: #{$order->order_number}\n\n";

// 4. Initier le paiement
echo "4. Initiation paiement...\n";
$service = app(\App\Services\MobileMoneyService::class);
$externalRef = 'ORDER_' . $order->id . '_' . time();

$result = $service->collect([
    'external_ref' => $externalRef,
    'amount' => $order->total_amount,
    'currency' => $order->currency,
    'payer_phone' => '+242067230202',
    'description' => 'Test Tinker',
]);

if ($result['success']) {
    echo "  ✓ Paiement initié avec succès\n";
    echo "  Transaction ID: " . ($result['data']['transaction_id'] ?? 'N/A') . "\n";
    echo "  External Ref: " . ($result['data']['external_ref'] ?? $externalRef) . "\n";
    echo "  Statut: " . ($result['data']['status'] ?? 'N/A') . "\n";

    // Mettre à jour la commande
    $order->update([
        'payment_status' => 'pending',
        'payment_transaction_id' => $result['data']['transaction_id'] ?? null,
        'payment_external_ref' => $result['data']['external_ref'] ?? $externalRef,
    ]);
} else {
    echo "  ✗ Échec: " . $result['message'] . "\n";
}

echo "\n=== FIN DU TEST ===\n";
```

---

## Notes Importantes

1. **Environnement de Test** : Assurez-vous d'utiliser des credentials de test, pas de production
2. **Numéros de Téléphone** : Utilisez des numéros valides pour vos tests
3. **Montants** : Vérifiez les montants minimum acceptés par l'API
4. **Logs** : Consultez toujours `storage/logs/laravel.log` pour le débogage

---

**Date de création :** 22 Octobre 2024
**Version :** 1.0 (Corrigée avec external_ref et app_id)
