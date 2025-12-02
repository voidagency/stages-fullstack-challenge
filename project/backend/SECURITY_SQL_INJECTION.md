# Protections contre les Injections SQL

## Modifications apportées

### 1. **ArticleController::search()** - Protection renforcée
- ✅ Remplacement de `whereRaw()` par `where()` et `orWhere()` (Eloquent pur)
- ✅ Validation stricte avec `$request->validate()` (min:1, max:255)
- ✅ Sanitization avec `strip_tags()` et `trim()`
- ✅ Limite de résultats : 100 articles maximum
- ✅ Paramètres automatiquement bindés par Eloquent (protection native)

### 2. **ImageUploadController::delete()** - Protection Path Traversal
- ✅ Validation stricte du chemin
- ✅ Sanitization pour empêcher `../` et autres attaques
- ✅ Vérification que le chemin reste dans les dossiers autorisés

### 3. **SecurityHeaders Middleware** - Protection globale
- ✅ Détection automatique des tentatives d'injection SQL
- ✅ Logging des activités suspectes (IP, URL, paramètres)
- ✅ Headers de sécurité HTTP (X-Frame-Options, X-XSS-Protection, etc.)

### 4. **Autres contrôleurs** - Déjà sécurisés
- ✅ CommentController : utilise Eloquent pur
- ✅ AuthController : utilise Eloquent avec validation
- ✅ StatsController : utilise Eloquent avec jointures sécurisées

## Bonnes pratiques implémentées

### ✅ Utiliser Eloquent plutôt que SQL raw
```php
// ❌ MAUVAIS (vulnérable)
DB::select("SELECT * FROM users WHERE email = '" . $email . "'");

// ❌ RISQUÉ (même avec whereRaw)
Article::whereRaw("title LIKE '%" . $query . "%'");

// ✅ BON (Eloquent pur avec binding automatique)
Article::where('title', 'LIKE', '%' . $query . '%')->get();

// ✅ BON (requête préparée explicite)
DB::select("SELECT * FROM users WHERE email = ?", [$email]);
```

### ✅ Toujours valider les inputs
```php
$validated = $request->validate([
    'q' => 'required|string|min:1|max:255',
]);
```

### ✅ Sanitizer les données utilisateur
```php
$query = strip_tags($query);
$query = trim($query);
```

### ✅ Limiter les résultats
```php
->limit(100) // Éviter les abus
```

## Tests de sécurité

### Test 1 : Injection SQL de base
```bash
curl "http://localhost:8000/api/articles/search?q=%27%20OR%20%271%27%3D%271"
# Attendu : [] (aucun résultat, pas d'injection)
```

### Test 2 : UNION SELECT
```bash
curl "http://localhost:8000/api/articles/search?q=%27%20UNION%20SELECT%20id,%20email,%20password,%201,%20null,%20null,%20now(),%20now()%20FROM%20users%20%23"
# Attendu : [] (requête bloquée, tentative loggée)
```

### Test 3 : Recherche légitime
```bash
curl "http://localhost:8000/api/articles/search?q=caf%C3%A9"
# Attendu : Articles contenant "café"
```

## Monitoring

Les tentatives d'injection SQL sont automatiquement détectées et loggées dans :
- `storage/logs/laravel.log`

Format du log :
```json
{
  "message": "Potential SQL injection attempt detected",
  "ip": "192.168.1.1",
  "url": "http://localhost:8000/api/articles/search?q=...",
  "parameter": "q",
  "value": "' OR '1'='1",
  "user_agent": "Mozilla/5.0..."
}
```

## Conformité

✅ **OWASP Top 10** - Protection contre A03:2021 (Injection)  
✅ **CWE-89** - SQL Injection Prevention  
✅ **PCI DSS** - Requirement 6.5.1  
✅ **RGPD** - Protection des données personnelles
