<?php

namespace AnourValar\EloquentValidation\Console\Commands;

use Illuminate\Console\Command;

class ModelValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:validate {--dirty} {--ignore-configuration} {--except=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all models';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $models = $this->getModels(
            app_path($this->modelsDirectory()),
            app()->getNamespace() . $this->modelsDirectory('\\'),
            (bool) $this->modelsDirectory()
        );
        $models = array_diff($models, explode(',', (string) $this->option('except')));

        $bar = $this->output->createProgressBar(count($models));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('');
        $bar->display();

        if ($this->option('dirty')) {
            \Illuminate\Database\Eloquent\Model::unguard();
        }

        foreach ($models as $model) {
            $bar->setMessage($model);

            if (! $this->option('ignore-configuration')) {
                $this->checkConfiguration(new $model());
            }

            foreach ($model::all() as $item) {
                if ($this->option('dirty')) {
                    $attributes = $item->getAttributes();
                    $item->setRawAttributes([], true); // sync
                    $item->setRawAttributes($attributes, false);
                }

                $this->validate($item);
            }

            $bar->advance();
        }

        $bar->setMessage('');
        $bar->finish();
        $this->output->newLine();

        return Command::SUCCESS;
    }

    /**
     * @param string $path
     * @param string $namespace
     * @param bool $recursive
     * @return array
     */
    protected function getModels(string $path, string $namespace, bool $recursive): array
    {
        $result = [];

        foreach (scandir($path) as $item) {
            if (in_array($item, ['.', '..'])) {
                continue;
            }

            if ($recursive && is_dir("$path/$item")) {
                $result = array_merge($result, $this->getModels("$path/$item", $namespace."$item\\", true));
            }

            if (! is_file("$path/$item")) {
                continue;
            }

            if (mb_strtolower(pathinfo($item, PATHINFO_EXTENSION)) != 'php') {
                continue;
            }

            $class = $namespace . pathinfo($item, PATHINFO_FILENAME);
            if (! in_array(\AnourValar\EloquentValidation\ModelTrait::class, $this->getTraits($class))) {
                continue;
            }

            $result[] = $class;
        }

        return $result;
    }

    /**
     * @param string $suffix
     * @return string
     */
    protected function modelsDirectory(string $suffix = '')
    {
        return is_dir(app_path('Models')) ? ('Models' . $suffix) : '';
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @throws \Illuminate\Validation\ValidationException
     * @return void
     */
    protected function validate(\Illuminate\Database\Eloquent\Model $model): void
    {
        try {
            $model->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            /** @psalm-suppress ForbiddenCode */
            dump($e->validator->errors()->all(), $e->validator->getData());
            throw $e;
        }
    }

    /**
     * @param string $class
     * @return array
     */
    protected function getTraits(string $class)
    {
        $traits = class_uses($class);

        foreach (class_parents($class) as $parent) {
            $traits = array_merge($traits, class_uses($parent));
        }

        return $traits;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @throws \LogicException
     * @return void
     */
    protected function checkConfiguration(\Illuminate\Database\Eloquent\Model $model): void
    {
        $modelName = get_class($model);
        $collection = [];
        $configurationAttributes = $model->extractAttributesListFromConfiguration();

        foreach ($configurationAttributes as $name => $value) {
            if (! is_array($value)) {
                throw new \LogicException("$name is not set");
            }

            if ($name == 'unique') {

                $flat = [];
                foreach ($value as &$batch) {
                    sort($batch);
                    $flat = array_merge($flat, $batch);
                }
                unset($batch);

                if (count($value) != count(array_unique($value, SORT_REGULAR))) {
                    throw new \LogicException("[$modelName] Duplicates for \"$name\"");
                }

                $collection = array_merge($collection, $flat);

            } elseif ($name == 'attribute_names') {

                foreach ($value as &$item) {
                    $item = explode('.', $item)[0];
                }
                unset($item);

                $collection = array_merge($collection, $value);

                $diff = array_diff(array_keys($model->getCasts()), $value);
                if ($diff) {
                    throw new \LogicException('['.$modelName.'] Missed attribute in the attribute names: ' . implode(', ', $diff));
                }

            } elseif ($name == 'jsonNested') {

                foreach ($value as $item) {
                    $diff = array_diff(array_keys($item), ['jsonb', 'nullable', 'purges', 'types', 'sorts', 'lists']);
                    if ($diff) {
                        throw new \LogicException('['.$modelName.'] Unsupported options: ' . implode(', ', $diff));
                    }

                    $checks = [];
                    $checks = array_merge($checks, ($item['nullable'] ?? []));
                    $checks = array_merge($checks, ($item['purges'] ?? []));
                    $checks = array_merge($checks, array_keys($item['types'] ?? []));
                    $checks = array_merge($checks, ($item['sorts'] ?? []));
                    $checks = array_merge($checks, ($item['lists'] ?? []));

                    foreach ($checks as $path) {
                        if ($path != '*' && explode('.', $path)[0] != '$') {
                            throw new \LogicException(
                                '['.$modelName.'] JsonPath must starts with "$.<path>". Given: ' . $path
                            );
                        }
                    }

                    foreach (($item['types'] ?? []) as $type) {
                        if ($type != mb_strtolower($type)) {
                            throw new \LogicException(
                                '['.$modelName.'] JsonPath type must be in lower case. Given: ' . $type
                            );
                        }
                    }
                }

                $collection = array_merge($collection, array_keys($value));

            } else {

                if (count($value) != count(array_unique($value, SORT_REGULAR))) {
                    throw new \LogicException("[$modelName] Duplicates for \"$name\": " . implode(', ', $value));
                }

                $collection = array_merge($collection, $value);

            }
        }

        $diff = array_unique(array_diff($collection, array_keys($model->getCasts()), ['pivot']));
        if ($diff) {
            throw new \LogicException('['.$modelName.'] Unrepresented attribute in the casts: ' . implode(', ', $diff));
        }

        if ($common = array_intersect($configurationAttributes['computed'], $configurationAttributes['unchangeable'])) {
            throw new \LogicException('['.$modelName.'] Common attributes in computed and unchangeable properties: ' . implode(', ', $common));
        }
    }
}
