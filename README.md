# Cached (More for Laravel)

A cache decorator that feels like **more** Laravel.

## Highlights

### Usage with helper

> Examples

```
// Fetch a model from the cache
$user = cached(User::class, 1);                 // App\User

// The default decorated class or 
// CACHE_DECORATOR specified on model.
$u = cached(User::class, 1, $decorate = true)   // More\Laravel\Cached\CacheDecorator

// A specific decorator to be returned
$u = cached(User::class, 1, Dashboard::class);  // App\Presenters\Dashboard

// A specific decorator to be returned
$u = cachedOrFail(User::class, 200000, Dashboard::class);  // throws ModelNotFoundException
```

### Usage with the macro

> Examples

```
// Find a model from the cache / db
$user = User::cached($id = 1);                  // App\User

// Find or fail from the cache /db 
$user = User::cachedOrFail($id = 200000);       // throws ModelNotFoundException

// Fail with exception or decorate.
$u = User::cachedOrFail($id = 1)->decorate()    // More\Laravel\Cached\CacheDecorator

// Param can be used when model may not be found
$u = User::cached($id = 1, $decorate = true)    // More\Laravel\Cached\CacheDecorator

// A specific decorator to be returned
$u = User::cachedOrFail($id = 1)                // App\Presenters\Dashboard
    ->decorate(Dashboard::class);
    
// A specific decorator to be returned
$u = User::cached($id = 1, Dashboard::class);   // App\Presenters\Dashboard
```

## More on Decorators

> A basic `CacheDecator` is included by default. But you can publish the config to switch the global default. 

### Default `CacheDecorator`

```
$ php artisan vendor:publish --provider="\More\Laravel\Cached\Support\CachedServiceProvider"
```

### Model constant `CACHE_DECORATOR`

> If your decorator demands various cached methods for your model, you can override the global behavior on each model as well.

```
class User extends Model
{
    const CACHE_DECORATOR = \App\Metrics\UserMetrics::class;
}

---

$user->decorate();                              // \App\Metrics\UserMetrics
```

### Decorated on run-time

> As you've already seen in the examples at the top, you can specify a decorator you want back at run-time.

## Suggestions

This pattern plays very nice with `hemp/presenter`. Just extend his `Presenter` class, add the trait included, and overload construct.

After some more testing, I'll probably add `__call` and `__callStatic` and maybe some other goodies to ease passing around decorators to various Laravel utilities.

Additionally, a overloading route-model binding with a drop-in replacement would be cool.

More testing is needed.

## Composer

    $ composer require dan/laravel-cached dev-master

## Contributors

- [Diogo Gomes](https://github.com/diogogomeswww)

## License

MIT.
