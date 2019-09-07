<?php

namespace AnourValar\EloquentValidation\Console\Commands;

class ModelMakeCommand extends \Illuminate\Foundation\Console\ModelMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:model-validation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class [eloquent-validation]';

    /**
     * {@inheritDoc}
     * @see \Illuminate\Foundation\Console\ModelMakeCommand::getStub()
     */
    protected function getStub()
    {
        if (!$this->option('pivot')) {
            return __DIR__.'/../../resources/model.stub';
        }

        return parent::getStub();
    }
}
