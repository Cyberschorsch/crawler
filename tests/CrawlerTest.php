<?php

namespace tests;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Loader\PoliteHttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\GroupInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use Mockery;
use Psr\Log\LoggerInterface;

function helper_getDummyCrawler(): Crawler
{
    return new class () extends Crawler {
        public function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('FooBot');
        }

        public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return Mockery::mock(PoliteHttpLoader::class);
        }
    };
}

test(
    'The methods to define UserAgent, Logger and Loader instances are called in construct and the getter methods ' .
    'always return the same instance.',
    function () {
        $crawler = new class () extends Crawler {
            public int $userAgentCalled = 0;
            public int $loggerCalled = 0;
            public int $loaderCalled = 0;

            protected function userAgent(): UserAgentInterface
            {
                $this->userAgentCalled += 1;

                return new class ('FooBot') extends BotUserAgent {
                    public string $testProperty = 'foo';
                };
            }

            protected function logger(): LoggerInterface
            {
                $this->loggerCalled += 1;

                return new class () extends CliLogger {
                    public string $testProperty = 'foo';
                };
            }

            protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
            {
                $this->loaderCalled += 1;

                return new class ($userAgent, null, $logger) extends PoliteHttpLoader {
                    public string $testProperty = 'foo';
                };
            }
        };
        expect($crawler->getUserAgent()->testProperty)->toBe('foo'); // @phpstan-ignore-line
        expect($crawler->getLogger()->testProperty)->toBe('foo');  // @phpstan-ignore-line
        expect($crawler->getLoader()->testProperty)->toBe('foo');  // @phpstan-ignore-line
        expect($crawler->userAgentCalled)->toBe(1);
        expect($crawler->loggerCalled)->toBe(1);
        expect($crawler->loaderCalled)->toBe(1);

        $crawler->getUserAgent()->testProperty = 'bar'; // @phpstan-ignore-line
        $crawler->getLogger()->testProperty = 'bar'; // @phpstan-ignore-line
        $crawler->getLoader()->testProperty = 'bar'; // @phpstan-ignore-line
        $crawler->addStep(Http::get()); // adding steps passes on logger and loader, should use the same instances

        expect($crawler->getUserAgent()->testProperty)->toBe('bar'); // @phpstan-ignore-line
        expect($crawler->getLogger()->testProperty)->toBe('bar');  // @phpstan-ignore-line
        expect($crawler->getLoader()->testProperty)->toBe('bar');  // @phpstan-ignore-line
        expect($crawler->userAgentCalled)->toBe(1);
        expect($crawler->loggerCalled)->toBe(1);
        expect($crawler->loaderCalled)->toBe(1);
    }
);

test('Using the input method, you set the input data for the first step', function () {
    $crawler = new class () extends Crawler {
        protected function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('CrwlrBot');
        }

        protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return Mockery::mock(LoaderInterface::class);
        }
    };

    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield $input->get();
        }
    };

    $crawler->input('https://www.example.com');
    $crawler->addStep($step);
    $results = helper_generatorToArray($crawler->run());
    expect($results[0]->toArray()['unnamed'])->toBe('https://www.example.com');

    $crawler->input(['https://www.crwl.io', 'https://www.otsch.codes']);
    $results = helper_generatorToArray($crawler->run());
    expect($results[0]->toArray()['unnamed'])->toBe('https://www.crwl.io');
    expect($results[1]->toArray()['unnamed'])->toBe('https://www.otsch.codes');
});

test('The static loop method wraps a Step in a LoopStep object', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('invokeStep')->withArgs(function (Input $input) {
        return $input->get() === 'foo';
    });
    $loop = Crawler::loop($step);
    $loop->invokeStep(new Input('foo'));
});

test('You can add steps and the Crawler class passes on its Logger and also its Loader if needed', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->addStep($step);

    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $crawler->addStep($step);
});

test('You can add steps and they are invoked when the Crawler is run', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('👍🏻')]));
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('resultDefined')->once()->andReturn(false);
    $crawler = helper_getDummyCrawler();
    $crawler->addStep($step);
    $crawler->input('randomInput');

    $results = $crawler->run();
    $results->current();
});

test('You can add step groups and the Crawler class passes on its Logger and Loader', function () {
    $group = Mockery::mock(GroupInterface::class);
    $group->shouldReceive('addLogger')->once();
    $group->shouldReceive('addLoader')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->addGroup($group);
});

test('You can add a parallel step group and it is invoked when the Crawler is run', function () {
    $group = Mockery::mock(GroupInterface::class);
    $group->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('👍🏻')]));
    $group->shouldReceive('addLogger')->once();
    $group->shouldReceive('addLoader')->once();
    $group->shouldReceive('resultDefined')->once()->andReturn(false);
    $crawler = helper_getDummyCrawler();
    $crawler->addGroup($group);
    $crawler->input('randomInput');

    $results = $crawler->run();
    $results->current();
});

test('Result objects are created when defined and passed on through all the steps', function () {
    $crawler = helper_getDummyCrawler();

    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'yo';
        }
    };

    $crawler->addStep($step->initResultResource('someResource')->resultResourceProperty('prop1'));

    $step2 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'lo';
        }
    };

    $crawler->addStep($step2->resultResourceProperty('prop2'));

    $step3 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'foo';
        }
    };

    $crawler->addStep($step3);

    $step4 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'bar';
        }
    };

    $crawler->addStep($step4);
    $crawler->input('randomInput');

    $results = $crawler->run();
    $results = helper_generatorToArray($results);

    expect($results[0])->toBeInstanceOf(Result::class);
    expect($results[0]->name())->toBe('someResource');
    expect($results[0]->toArray())->toBe([
        'prop1' => 'yo',
        'prop2' => 'lo',
    ]);
});

test('When final steps return an array you get all values in the defined Result resource', function () {
    $crawler = helper_getDummyCrawler();

    $step1 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'Donald';
        }
    };
    $crawler->addStep($step1->initResultResource('Ducks')->resultResourceProperty('parent'));

    $step2 = new class () extends Step {
        /**
         * @return Generator<array<string>>
         */
        protected function invoke(Input $input): Generator
        {
            yield ['Tick', 'Trick', 'Track'];
        }
    };
    $crawler->addStep($step2->resultResourceProperty('children'));
    $crawler->input('randomInput');

    $results = $crawler->run();

    expect($results->current()->toArray())->toBe([
        'parent' => 'Donald',
        'children' => ['Tick', 'Trick', 'Track'],
    ]);
    $results->next();
    expect($results->current())->toBeNull();
});
