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
    protected $signature = 'model:validate {--dirty} {--ignore-configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all models';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

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
                $this->checkConfiguration(new $model);
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
    }

    /**
     * @param string $path
     * @param string $namespace
     * @param boolean $recursive
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
            dump( $e->validator->errors()->all(), $e->validator->getData() );
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
        $collection = [];

        foreach ($model->extractAttributesListFromConfiguration() as $name => $value) {
            if ($name == 'unique') {

                    $flat = [];
                    foreach ($value as &$batch) {
                        sort($batch);
                        $flat = array_merge($flat, $batch);
                    }
                    unset($batch);

                    if (count($value) != count(array_unique($value, SORT_REGULAR))) {
                        throw new \LogicException("Duplicates for \"$name\"");
                    }

                    $collection = array_merge($collection, $flat);

            } else {

                if (count($value) != count(array_unique($value, SORT_REGULAR))) {
                    throw new \LogicException("Duplicates for \"$name\": " . implode(', ', $value));
                }

                $collection = array_merge($collection, $value);

            }
        }

        $diff = array_diff($collection, array_keys($model->getCasts()));
        if ($diff) {
            throw new \LogicException('Unpresent attributes in casts: ' . implode(', ', $diff));
        }
    }
}
