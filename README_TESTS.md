# Guide des Tests - Mobile Money Gateway

Ce guide explique comment tester facilement l'intégration Mobile Money.

---

## 🚀 Test Rapide (Recommandé)

### Utilisation du script test_payment.php

Le moyen le plus simple pour tester un paiement :

```bash
# Test avec un montant spécifique (en XAF)
php test_payment.php 500

# Test avec 100 XAF
php test_payment.php 100

# Test avec un montant aléatoire (100-5000 XAF)
php test_payment.php
```

### Ce que fait le script :

1. ✅ Vérifie la configuration
2. ✅ Crée/récupère un utilisateur de test
3. ✅ Crée une commande
4. ✅ Initie le paiement via l'API
5. ✅ Affiche les résultats en couleur
6. ✅ Met à jour la base de données

### Exemples de sortie :

**Succès :**
```
╔═══════════════════════════════════════════════════════════════╗
║                    ✅ SUCCÈS - PAIEMENT INITIÉ               ║
╚═══════════════════════════════════════════════════════════════╝

📋 Détails de la transaction :
  🆔 Transaction ID  : txn_68F9505476B4C
  🔗 External Ref    : ORDER_7_1761169492
  📊 Status          : PENDING
  📱 Opérateur       : MTN
  📞 Téléphone       : +242067230202

🎉 Le paiement a été initié avec succès !
```

---

## 🧪 Test Complet (Détaillé)

### Utilisation du script test_mobile_money.php

Pour un test plus détaillé avec statistiques :

```bash
php test_mobile_money.php
```

### Ce que fait le script :

1. Vérifie la configuration complète
2. Crée un utilisateur et une commande
3. Teste la génération de signature
4. Affiche des exemples cURL
5. Génère un token d'authentification
6. Affiche des statistiques détaillées

---

## 💡 Test avec Tinker (Pour développeurs)

### Lancement de Tinker

```bash
php artisan tinker
```

### Test Simple

```php
// Instancier le service
$service = app(\App\Services\MobileMoneyService::class);

// Initier un paiement
$result = $service->collect([
    'external_ref' => 'TEST_' . time(),
    'amount' => 500,
    'currency' => 'XAF',
    'payer_phone' => '+242067230202',
    'description' => 'Test rapide',
]);

// Afficher le résultat
dd($result);
```

### Test Complet

Consultez le fichier [TINKER_TEST_GUIDE.md](TINKER_TEST_GUIDE.md) pour plus d'exemples.

---

## 📊 Statistiques et Monitoring

### Voir toutes les transactions

```bash
php artisan tinker
```

```php
// Toutes les transactions
\App\Models\Order::whereNotNull('payment_transaction_id')->get();

// Statistiques
$total = \App\Models\Order::whereNotNull('payment_transaction_id')->count();
$montant = \App\Models\Order::whereNotNull('payment_transaction_id')->sum('total_amount');

echo "Total: $total transactions\n";
echo "Montant: $montant XAF\n";
```

---

## 🧹 Nettoyage des Données de Test

### Supprimer toutes les commandes de test

```bash
php artisan tinker
```

```php
// Supprimer les commandes de test
\App\Models\Order::where('order_number', 'LIKE', 'TEST%')->delete();

echo "Commandes de test supprimées\n";
```

---

## ⚙️ Configuration

### Variables d'environnement requises

Dans votre fichier `.env` :

```env
MOBILE_MONEY_API_URL=http://mobile-money-gateway.test/api/v1
MOBILE_MONEY_API_KEY=pk_votre_cle_api
MOBILE_MONEY_SECRET_KEY=sk_votre_secret
MOBILE_MONEY_APP_ID=votre_app_id
MOBILE_MONEY_CALLBACK_URL=https://votre-app.com/api/payment-callback
MOBILE_MONEY_TIMEOUT=30
```

### Vérifier la configuration

```bash
php artisan tinker --execute="
config('mobilemoney');
"
```

---

## 🐛 Dépannage

### Erreur : "Invalid signature"

**Solution :** Vérifiez que votre `MOBILE_MONEY_SECRET_KEY` est correcte.

```bash
php artisan config:clear
php test_payment.php 100
```

### Erreur : "App ID is required"

**Solution :** Ajoutez `MOBILE_MONEY_APP_ID` dans votre `.env`.

### Erreur de connexion

**Solution :** Vérifiez que l'URL de l'API est accessible.

```bash
curl http://mobile-money-gateway.test/api/v1/payments/collect
```

---

## 📝 Fichiers de Test Disponibles

| Fichier | Description | Usage |
|---------|-------------|-------|
| `test_payment.php` | Test rapide et simple | `php test_payment.php 500` |
| `test_mobile_money.php` | Test complet avec stats | `php test_mobile_money.php` |
| `TINKER_TEST_GUIDE.md` | Guide Tinker détaillé | Documentation |

---

## ✅ Checklist de Test

Avant de passer en production, testez :

- [ ] Paiement avec montant minimum (100 XAF)
- [ ] Paiement avec montant moyen (1000 XAF)
- [ ] Paiement avec montant élevé (5000+ XAF)
- [ ] Vérification de la configuration
- [ ] Signature HMAC validée
- [ ] Transaction enregistrée en BDD
- [ ] Payment URL générée
- [ ] Opérateur détecté correctement
- [ ] Callback URL configurée
- [ ] Logs fonctionnels

---

## 🎯 Exemples de Commandes Utiles

### Test rapide avec différents montants

```bash
# Tester 100 XAF
php test_payment.php 100

# Tester 500 XAF
php test_payment.php 500

# Tester 1000 XAF
php test_payment.php 1000

# Tester 5000 XAF
php test_payment.php 5000
```

### Exécuter plusieurs tests

```bash
# Bash script pour tester plusieurs montants
for amount in 100 500 1000 2500 5000; do
    echo "Test avec $amount XAF..."
    php test_payment.php $amount
    sleep 2
done
```

---

## 📞 Support

- **Documentation complète :** [API_INTEGRATION_GUIDE.md](API_INTEGRATION_GUIDE.md)
- **Tests Tinker :** [TINKER_TEST_GUIDE.md](TINKER_TEST_GUIDE.md)
- **Corrections appliquées :** Voir les commits récents

---

**Version :** 1.0
**Dernière mise à jour :** 22 Octobre 2025
