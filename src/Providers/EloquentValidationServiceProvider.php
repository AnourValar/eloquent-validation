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
        // validation rules
        $this->addScalarRule();
        $this->addConfigRule();
        $this->addAvailableKeysRule();

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

    /**
     * @return void
     */
    private function addScalarRule() : void
    {
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
    }

    /**
     * @return void
     */
    private function addConfigRule() : void
    {
        \Validator::extend('config', function ($attribute, $value, $parameters, $validator)
        {
            if (empty($parameters[0])) {
                throw new \LogicException('Parameter required for "config" rule!');
            }

            if (is_scalar($value)) {
                return isset(config($parameters[0])[$value]);
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_null($item) && !empty($parameters[1])) {
                        continue;
                    }

                    if (!is_scalar($item) || !isset(config($parameters[0])[$item])) {
                        return false;
                    }
                }

                if (count($value) != count(array_unique($value))) {
                    return false;
                }

                return true;
            }

            return false;
        });

        \Validator::replacer('config', function ($message, $attribute, $rule, $parameters, $validator)
        {
            return trans(
                'eloquent-validation::validation.config',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }

    /**
     * @return void
     */
    private function addAvailableKeysRule() : void
    {
        \Validator::extend('available_keys', function($attribute, $value, $parameters, $validator)
        {
            return ( is_array($value) && !array_diff_key($value, array_combine($parameters, $parameters)) );
        });

        \Validator::replacer('available_keys', function ($message, $attribute, $rule, $parameters, $validator)
        {
            return trans(
                'eloquent-validation::validation.available_keys',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }
}
