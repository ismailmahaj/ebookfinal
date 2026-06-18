# Erreur 500 sur /api/auth/me : base de données

## Cause

L’erreur **500** sur `GET /api/auth/me` vient de Laravel qui ne peut pas se connecter à MySQL :

```text
Access denied for user 'ismse2685832'@'localhost' (using password: YES)
```

Le `.env` contient les identifiants de la base **LWS (production)**. En local, ce compte n’existe pas sur ton MySQL (ou MySQL n’est pas utilisé), donc toute route qui touche à la BDD (dont `/auth/me`) renvoie 500.

## Solution 1 : Utiliser SQLite en local (recommandé)

1. Dans **laravel-api**, ouvre `.env`.

2. Remplace la partie base de données par :

```env
DB_CONNECTION=sqlite
DB_DATABASE=/Users/ismailmahaj/Desktop/E-book/laravel-api/database/database.sqlite
```

(Adapte le chemin si ton projet n’est pas dans `Desktop/E-book`.)

3. Commente ou supprime les anciennes lignes MySQL :  
   `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` (et l’ancien `DB_DATABASE` si tu le laisses).

4. Créer la base et les tables :

```bash
cd laravel-api
touch database/database.sqlite
php artisan migrate
```

5. Redémarrer le serveur : `php artisan serve`.

Ensuite, inscription / connexion et `GET /api/auth/me` devraient fonctionner en local.

## Solution 2 : Utiliser MySQL en local

- Installer MySQL (ou MAMP/XAMPP).
- Créer une base et un utilisateur.
- Mettre dans `.env` les vraies valeurs pour `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Lancer `php artisan migrate`.

## Production (LWS)

Sur LWS, garde **MySQL** dans `.env` avec les identifiants fournis par l’hébergeur (comme actuellement). Ne passe en SQLite que pour le développement sur ta machine.
