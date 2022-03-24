<?php

namespace tests\Steps;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Loop;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Generator;
use Mockery;
use function tests\helper_arrayToGenerator;
use function tests\helper_generatorToArray;
use function tests\helper_traverseIterable;

test('You can add a step and it passes on the logger', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldNotReceive('addLoader');
    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step);
});

test('It also passes on a new logger to all steps when the logger is added after the steps', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $group = new Group();
    $group->addStep($step1);
    $group->addStep($step2);
    $group->addLogger(new CliLogger());
});

test('It also passes on the loader to the step when addLoader method exists in step', function () {
    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));
    $group->addStep($step);
});

test('It also passes on a new loader to all steps when it is added after the steps', function () {
    $step1 = Mockery::mock(LoadingStepInterface::class);
    $step1->shouldReceive('addLoader')->once();
    $step2 = Mockery::mock(LoadingStepInterface::class);
    $step2->shouldReceive('addLoader')->once();
    $group = new Group();
    $group->addStep($step1);
    $group->addStep($step2);
    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));
});

test('The factory method returns a Group object instance', function () {
    expect(Crawler::group())->toBeInstanceOf(Group::class);
});

test('You can add multiple steps and invokeStep calls all of them', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger', 'cascades', 'invokeStep')->once();
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger', 'cascades', 'invokeStep')->once();
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger', 'cascades', 'invokeStep')->once();

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    helper_traverseIterable($group->invokeStep(new Input('foo')));
});

test('It returns the results of all steps when invoked', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('cascades')->once()->andReturn(true);
    $step1->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('1')]));
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('cascades')->once()->andReturn(true);
    $step2->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('2')]));
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger')->once();
    $step3->shouldReceive('cascades')->once()->andReturn(true);
    $step3->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('3')]));

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    $output = $group->invokeStep(new Input('foo'));
    $output = helper_generatorToArray($output);

    expect($output)->toBeArray();
    expect($output)->toHaveCount(3);
    expect($output[0])->toBeInstanceOf(Output::class);
    expect($output[0]->get())->toBe('1');
    expect($output[1])->toBeInstanceOf(Output::class);
    expect($output[1]->get())->toBe('2');
    expect($output[2])->toBeInstanceOf(Output::class);
    expect($output[2]->get())->toBe('3');
});

test(
    'It combines the outputs of all it\'s steps into one output containing an array when combineToSingleOutput is used',
    function () {
        $step1 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'lorem';
            }
        };
        $step2 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'ipsum';
                yield 'dolor';
            }
        };
        $step3 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'sit';
            }
        };

        $group = new Group();
        $group->addLogger(new CliLogger());
        $group->addStep($step1)->addStep($step2)->addStep($step3);
        $group->combineToSingleOutput();
        $output = $group->invokeStep(new Input('gogogo'));
        $output = helper_generatorToArray($output);

        expect($output)->toBeArray();
        expect($output)->toHaveCount(1);
        expect($output[0])->toBeInstanceOf(Output::class);
        expect($output[0]->get())->toBe([
            'lorem',
            ['ipsum', 'dolor'],
            'sit'
        ]);
    }
);

test(
    'When mapping steps to the Result object and also combining to a single output, the resultKeys are also used in ' .
    'the output array',
    function () {
        $step1 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'ich';
            }
        };
        $step2 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'bin';
                yield 'ein';
            }
        };
        $step3 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'berliner';
            }
        };

        $group = new Group();
        $group->addLogger(new CliLogger());
        $group->addStep('foo', $step1)->addStep('bar', $step2)->addStep('baz', $step3);
        $group->combineToSingleOutput();
        $output = $group->invokeStep(new Input('https://www.gogo.go'));
        $output = helper_generatorToArray($output);

        expect($output)->toBeArray();
        expect($output)->toHaveCount(1);
        expect($output[0])->toBeInstanceOf(Output::class);
        expect($output[0]->get())->toBe([
            'foo' => 'ich',
            'bar' => ['bin', 'ein'],
            'baz' => 'berliner',
        ]);
        expect($output[0]->result)->toBeInstanceOf(Result::class);
        expect($output[0]->result->toArray())->toBe([
            'foo' => 'ich',
            'bar' => ['bin', 'ein'],
            'baz' => 'berliner',
        ]);
    }
);

test('It doesn\'t output anything when the dontCascade method was called', function () {
    $step1 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield 'something';
        }
    };
    $step2 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $number) {
                yield $number;
            }
        }
    };

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep('foo', $step1)->addStep('bar', $step2);

    $results = helper_generatorToArray($group->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(11);

    $group->dontCascade();
    $results = helper_generatorToArray($group->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(0);

    // Also doesn't yield when a step is added after the dontCascade() call
    $newStep = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield 'something';
        }
    };
    $group->addStep($newStep);

    $results = helper_generatorToArray($group->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(0);
});

test('It doesn\'t return the output of a step when the dontCascade method was called on that step', function () {
    $step1 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield 'foo';
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield 'bar';
        }
    };

    $step2->dontCascade();

    $group = new Group();

    $group->addLogger(new CliLogger())->addStep('foo', $step1)->addStep($step2);

    $outputs = helper_generatorToArray($group->invokeStep(new Input('foo')));

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe('foo');
});

test(
    'It doesn\'t contain the output of a step when the dontCascade method was called on that step and the Group\'s ' .
    'output is combined',
    function () {
        $step1 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'abc';
            }
        };

        $step2 = new class () extends Step {
            protected function invoke(Input $input): Generator
            {
                yield 'def';
            }
        };

        $step2->dontCascade();

        $group = new Group();

        $group->addLogger(new CliLogger())
            ->addStep('one', $step1)
            ->addStep('two', $step2)
            ->combineToSingleOutput();

        $outputs = helper_generatorToArray($group->invokeStep(new Input('foo')));

        expect($outputs)->toHaveCount(1);

        expect($outputs[0]->get())->toBe(['one' => 'abc']);
    }
);

test('You can update the input for further steps with the output of a step that is before those steps', function () {
    $step1 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield ' rocks';
        }
    };
    $step1->updateInputUsingOutput(function (Input $input, Output $output) {
        return $input->get() . $output->get();
    });
    $step2 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield $input->get();
        }
    };

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2);

    $outputs = helper_generatorToArray($group->invokeStep(new Input('crwlr.software')));
    expect($outputs)->toHaveCount(2);
    expect($outputs[1]->get())->toBe('crwlr.software rocks');
});

test('Updating the input for further steps with output also works with loop steps', function () {
    $step1 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield ' Jump!';
        }
    };
    $step1->updateInputUsingOutput(function (Input $input, Output $output) {
        return $input->get() . $output->get();
    });
    $step1 = new Loop($step1);
    $step1->maxIterations(2);
    $step2 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield $input->get();
        }
    };

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2);

    $outputs = helper_generatorToArray($group->invokeStep(new Input('The Mac Dad will make ya:')));
    expect($outputs)->toHaveCount(3);
    expect($outputs[2]->get())->toBe('The Mac Dad will make ya: Jump! Jump!');
});

test('Updating the input for further steps also works when combining the group output to a single output', function () {
    $step1 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield ' Jump!';
        }
    };
    $step1->updateInputUsingOutput(function (Input $input, Output $output) {
        return $input->get() . $output->get();
    });
    $step1 = new Loop($step1);
    $step1->maxIterations(2);
    $step2 = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield $input->get();
        }
    };

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2);
    $group->combineToSingleOutput();

    $outputs = helper_generatorToArray($group->invokeStep(new Input('The Mac Dad will make ya:')));
    expect($outputs)->toHaveCount(1);
    expect($outputs[0]->get())->toBe([
        [
            ' Jump!',
            ' Jump!',
        ],
        'The Mac Dad will make ya: Jump! Jump!'
    ]);
});
