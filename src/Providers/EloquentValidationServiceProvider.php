<?php

namespace AnourValar\EloquentValidation\Providers;

use Illuminate\Support\ServiceProvider;

class EloquentValidationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // "scalar" validation
        \Validator::extend('scalar', function ($attribute, $value, $parameters, $validator)
        {
            return ( is_scalar($value) || is_null($value) || (is_object($value) && method_exists($value, '__toString')) );
        });

        \Validator::replacer('scalar', function ($message, $attribute, $rule, $parameters, $validator) {
            return trans(
                'eloquent-validation::validation.scalar',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });


        // "config" validation
        \Validator::extend('config', function ($attribute, $value, $parameters, $validator)
        {
            if (empty($parameters[0])) {
                throw new \Exception('Parameter required for "config" rule!');
            }

            return isset(config($parameters[0])[$value]);
        });

        \Validator::replacer('config', function ($message, $attribute, $rule, $parameters, $validator)
        {
            return trans(
                'eloquent-validation::validation.config',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });


        // langs
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang/', 'eloquent-validation');
        $this->publishes([__DIR__.'/../resources/lang/' => resource_path('lang/vendor/eloquent-validation')]);


        // commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AnourValar\EloquentValidation\Console\Commands\ModelMakeCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }
}
