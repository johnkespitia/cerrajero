# Saitama

Saitama

## Run migrations
### Up Migrations
```shell
php artisan migrate
```
### Down Migrations
```shell
php artisan migrate:rollback
```

## Run Seeders basics
```shell
php artisan db:seed --class=RolStartup 
```

```shell
php artisan db:seed --class=UserStartup 
```

```shell
php artisan db:seed --class=TypeDocumentSeeder 
```

```shell
php artisan db:seed --class=PaymentMethods      
```

```shell
php artisan db:seed --class=CitiesSeeder 
```

```shell
php artisan db:seed --class=AttributesSeeder 
```

```shell
php artisan db:seed --class=ProviderTypesSeeder 
```

```shell
php artisan db:seed --class=OrderStatusSeeder 
```

```shell
php artisan db:seed --class=CategoriesSeeder 
```

```shell
php artisan db:seed --class=OrderPriceStatusSeeder 
```