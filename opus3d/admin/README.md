# Opus3D Admin Panel

Sistema di amministrazione per Opus3D con login e registrazione admin.

## Installazione

### 1. Database

1. Crea un database MySQL chiamato `opus3d` (o modifica il nome in `config.php`)
2. Importa il file `tables.sql` nella root del progetto:
   ```sql
   mysql -u root -p opus3d < tables.sql
   ```
   Oppure importalo tramite phpMyAdmin o il tuo client MySQL preferito.

### 2. Configurazione

Modifica il file `admin/config.php` con le tue credenziali database:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'opus3d');
```

### 3. Accesso

- **URL Login**: `http://localhost/opus3d/admin/login.php`
- **URL Registrazione**: `http://localhost/opus3d/admin/register.php`
- **URL Dashboard**: `http://localhost/opus3d/admin/index.php`

### 4. Account Admin Default

Dopo l'importazione del database, viene creato un account admin di default:

- **Username**: `admin`
- **Password**: `admin123`

**⚠️ IMPORTANTE**: Cambia immediatamente la password dopo il primo accesso!

## Struttura File

```
admin/
├── config.php          # Configurazione database e funzioni helper
├── login.php           # Pagina di login
├── register.php        # Pagina di registrazione
├── logout.php          # Logout e pulizia sessioni
├── index.php           # Dashboard principale
├── admin.css           # Stili per il pannello admin
└── README.md           # Questo file
```

## Funzionalità

### Login
- Autenticazione con username o email
- Opzione "Ricordami" con cookie sicuro
- Gestione sessioni
- Log delle attività

### Registrazione
- Validazione completa dei dati
- Hash password con `password_hash()`
- Controllo duplicati username/email
- Ruolo di default: `admin`

### Dashboard
- Statistiche generali (ordini, ricavi, prodotti, clienti)
- Lista ordini recenti
- Azioni rapide
- Navigazione sidebar

### Sicurezza
- Password hash con bcrypt
- Sanitizzazione input
- Protezione SQL injection (prepared statements)
- Sessioni sicure
- Log attività admin

## Tabelle Database

Il file `tables.sql` include tutte le tabelle necessarie:

- `admins` - Amministratori
- `customers` - Clienti
- `products` - Prodotti
- `orders` - Ordini
- `cart` - Carrello
- `categories` - Categorie
- E molte altre...

## Sviluppi Futuri

- Gestione prodotti
- Gestione ordini
- Gestione clienti
- Gestione amministratori
- Report e statistiche avanzate
- Impostazioni sistema

## Note

- Assicurati che PHP sia configurato con estensioni: `mysqli`, `session`
- Il sistema richiede PHP 7.4 o superiore
- Le sessioni vengono gestite automaticamente
- I log delle attività vengono salvati nella tabella `admin_logs`

