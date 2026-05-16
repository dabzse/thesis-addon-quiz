# Edu_Jobs diplomammunkához kiegészítő kvíz alkalmazás

Ez a projekt a kvíz alkalmazás backend (PHP) és frontend (Svelte) részét tartalmazza.

[Bemutatóvideó link](https://youtube.com/watch?v=Ji7bOf5txgA)

Folytatás: [FSF.hu SysAdminDay Quiz](https://gitlab.com/dabzse/fsfhu-quiz)

## Fáljstruktúra és konfiguráció

A projekt konfigurációja egyetlen helyen, a **`backend/.env`** fájlban történik. (A főkönyvtárban lévő `.env.example` fájl szolgál sablonként).

### Környezeti változók (.env) beállítása

1. Másold a `.env.example` fájlt a `backend/` mappába `.env` néven!

   ```bash
   cp .env.example backend/.env
   ```

2. Nyisd meg a `backend/.env` fájlt és töltsd ki a MariaDB / MySQL adataiddal.

| Változó      | Leírás                                  | Alapértelmezett |
| :----------- | :-------------------------------------- | :-------------- |
| `DB_HOST`    | Az adatbázis szerver címe               | `localhost`     |
| `DB_PORT`    | Az adatbázis szerver portja TCP esetén  | `3306`          |
| `DB_SOCKET`  | Unix socket útvonala \*                 | üres            |
| `DB_NAME`    | Az adatbázis neve                       | -               |
| `DB_USER`    | Adatbázis felhasználónév                | -               |
| `DB_PASS`    | Adatbázis jelszó \*\*                   | -               |

## Adatbázis kapcsolat működése

A `backend/src/Database/Connection.php` felel az adatbázis kapcsolatért.

1. Megkeresi a `backend/.env` fájlt és beolvassa a benne lévő változókat a `$_ENV` szuperglobális tömbbe.
2. Megpróbál felépíteni egy PDO-objektumot.
3. Ha kitöltötted a `DB_SOCKET`-et, a rendszer "unix socket" alapú kapcsolatot épít fel, ami Linux alatt stabilabb és gyorsabb lokális (azonos gépen futó) fejlesztés során.
4. Ha a `DB_SOCKET` üres, normál hálózati (TCP) csatlakozást épít a `DB_HOST` és `DB_PORT` használatával.

## Telepítés futtatás előtt

### Backend

Mivel a backend PHP-ra épül, ha használsz composert, előbb azt le kell futtatni:

```bash
cd backend
composer install
```

"Normál" futtatáshoz:

```bash
php -S localhost:8000
```

Teszteléshez vagy lokális futtatáshoz a beépített webszerver indítása:

```bash
php -S localhost:8000 -t public
```

### Frontend

A Svelte alkalmazás függőségeinek telepítése és futtatás (pnpm használatával):

```bash
cd frontend
pnpm install
pnpm dev
```

> \* : UNIX SOCKET: (pl: `/var/run/mysqld/mysqld.sock` vagy `/var/lib/mysql/mysql.sock`). **Ha ezt kitöltöd, a `DB_HOST` és `DB_PORT` figyelmen kívül lesz hagyva**, felülíródik a gyorsabb helyi socket-tel.
>
> \*\* : adatbázis jelszó: (ha üresen hagyod, jelszó nélkül próbál belépni)
>
> **Figyelmeztetés az adatbázis jelszavaknál:** A MariaDB/MySQL alapértelmezetten tiltja a *jelszó nélküli* lokális bejelentkezést, még akkor is, ha jelszó nincs konkrétan megadva az adott felhasználóhoz (kivéve auth socket). Ha 500-as Internal Server Errort vagy `Access denied (using password: NO)` hibát kapsz, ellenőrizd a `DB_PASS` értékét a `backend/.env` fájlban, mert általában a kliensed (pl. DbGate) elmentett egyedi jelszóval kapcsolódik!
