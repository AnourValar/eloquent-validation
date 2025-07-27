<?php

namespace AnourValar\EloquentValidation\Providers;

use Illuminate\Support\ServiceProvider;

class EloquentValidationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // validation rules
        $this->addConfigRule();
        $this->addArrayKeysRule();
        $this->addArrayKeysOnlyRule();
        $this->addArrayKeysIdRule();
        $this->addNotEmpty();
        $this->updateDefaultRules();

        // langs
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang/', 'eloquent-validation');
        $this->publishes([__DIR__.'/../resources/lang/' => lang_path('vendor/eloquent-validation')]);

        // commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AnourValar\EloquentValidation\Console\Commands\ModelMakeCommand::class,
                \AnourValar\EloquentValidation\Console\Commands\ModelValidateCommand::class,
                \AnourValar\EloquentValidation\Console\Commands\ObserverMakeCommand::class,
            ]);
        }
    }

    /**
     * @return void
     */
    private function addConfigRule(): void
    {
        \Validator::extend('config', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters[0])) {
                throw new \LogicException('Parameter required for "config" rule!');
            }

            if (is_scalar($value)) {
                return isset(config($parameters[0])[$value]);
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (! is_scalar($item) || ! isset(config($parameters[0])[$item])) {
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

        \Validator::replacer('config', function ($message, $attribute, $rule, $parameters, $validator) {
            return trans(
                'eloquent-validation::validation.config',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }

    /**
     * @return void
     */
    private function addArrayKeysRule(): void
    {
        \Validator::extend('array_keys', function ($attribute, $value, $parameters, $validator) {
            $attribute .= '.';
            foreach (array_keys($validator->getRules()) as $item) {
                if (strpos($item, $attribute) !== 0) {
                    continue;
                }

                $item = substr($item, strlen($attribute));
                $item = explode('.', $item, 2);

                $parameters[] = $item[0];
            }
            $parameters = array_unique($parameters);

            return (is_array($value) && ! array_diff_key($value, array_combine($parameters, $parameters)));
        });

        \Validator::replacer('array_keys', function ($message, $attribute, $rule, $parameters, $validator) {
            return trans(
                'eloquent-validation::validation.array_keys',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }

    /**
     * @return void
     */
    private function addArrayKeysOnlyRule(): void
    {
        \Validator::extend('array_keys_only', function ($attribute, $value, $parameters, $validator) {
            return (is_array($value) && ! array_diff_key($value, array_combine($parameters, $parameters)));
        });

        \Validator::replacer('array_keys_only', function ($message, $attribute, $rule, $parameters, $validator) {
            return trans(
                'eloquent-validation::validation.array_keys',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }

    /**
     * @return void
     */
    private function addArrayKeysIdRule(): void
    {
        \Validator::extend('array_keys_id', function ($attribute, $value, $parameters, $validator) {
            $attribute .= '.';
            foreach (array_keys($validator->getRules()) as $item) {
                if (strpos($item, $attribute) !== 0) {
                    continue;
                }

                $item = substr($item, strlen($attribute));
                $item = explode('.', $item, 2);

                if (! is_numeric($item[0]) || $item[0] != (int) $item[0] || $item[0] < 1) {
                    return false;
                }
            }

            return true;
        });

        \Validator::replacer('array_keys_id', function ($message, $attribute, $rule, $parameters, $validator) {
            return trans(
                'eloquent-validation::validation.array_keys_id',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }

    /**
     * @return void
     */
    private function addNotEmpty(): void
    {
        \Validator::extendImplicit('not_empty', function ($attribute, $value, $parameters, $validator) {
            if (is_string($value)) {
                $value = trim($value);
            }

            return $value !== '';
        });

        \Validator::replacer('not_empty', function ($message, $attribute, $rule, $parameters, $validator) {
            return trans(
                'eloquent-validation::validation.not_empty',
                ['attribute' => $validator->getDisplayableAttribute($attribute)]
            );
        });
    }

    /**
     * @return void
     */
    private function updateDefaultRules(): void
    {
        \Validator::replacer('max', function ($message, $attribute, $rule, $parameters, $validator) {
            $value = $parameters[0];
            if (isset($parameters[1])) {
                $value = trans($parameters[1]);
            }

            return str_replace(':max', $value, $message);
        });

        \Validator::replacer('min', function ($message, $attribute, $rule, $parameters, $validator) {
            $value = $parameters[0];
            if (isset($parameters[1])) {
                $value = trans($parameters[1]);
            }

            return str_replace(':min', $value, $message);
        });
    }
}
