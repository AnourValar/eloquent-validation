<?php

namespace AnourValar\EloquentValidation\Console\Commands;

class ObserverMakeCommand extends \Illuminate\Foundation\Console\ObserverMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:observer-validation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new observer class [eloquent-validation]';

    /**
     * {@inheritDoc}
     * @see \Illuminate\Foundation\Console\ModelMakeCommand::getStub()
     */
    protected function getStub()
    {
        if ($this->option('model')) {
            return __DIR__.'/../../resources/observer.stub';
        }

        return parent::getStub();
    }
}
