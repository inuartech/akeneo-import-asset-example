## Akeneo Import Assets

Script to import images into Akeneo PIM via API.

### how to use

1. install dependencies
```bash
composer install
```

2. create the .env and add your credentials
```bash
cp .env-dist .env
```

3. run
```bash
php assets-import.php thumbnail --locale=en_US
```