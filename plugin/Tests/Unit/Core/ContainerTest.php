<?php

declare(strict_types=1);

namespace WpAgent\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use WpAgent\Core\Container;
use WpAgent\Exceptions\WpAgentException;

/**
 * Tests for the PSR-11 DI Container.
 *
 * @covers \WpAgent\Core\Container
 *
 * @package WpAgent\Tests\Unit\Core
 */
final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    // -------------------------------------------------------------------------
    // bind()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_binds_and_resolves_a_factory(): void
    {
        $this->container->bind('foo', fn ($c) => new \stdClass());

        $result = $this->container->get('foo');

        self::assertInstanceOf(\stdClass::class, $result);
    }

    /** @test */
    public function it_creates_new_instance_each_time_for_non_singleton(): void
    {
        $this->container->bind('foo', fn ($c) => new \stdClass());

        $a = $this->container->get('foo');
        $b = $this->container->get('foo');

        self::assertNotSame($a, $b);
    }

    // -------------------------------------------------------------------------
    // singleton()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_same_instance_for_singleton(): void
    {
        $this->container->singleton('foo', fn ($c) => new \stdClass());

        $a = $this->container->get('foo');
        $b = $this->container->get('foo');

        self::assertSame($a, $b);
    }

    // -------------------------------------------------------------------------
    // instance()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_registers_and_returns_a_pre_built_instance(): void
    {
        $obj = new \stdClass();
        $this->container->instance('myobj', $obj);

        self::assertSame($obj, $this->container->get('myobj'));
    }

    // -------------------------------------------------------------------------
    // has()
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_true_for_registered_id(): void
    {
        $this->container->bind('foo', fn ($c) => new \stdClass());

        self::assertTrue($this->container->has('foo'));
    }

    /** @test */
    public function it_returns_false_for_unregistered_id(): void
    {
        self::assertFalse($this->container->has('bar'));
    }

    // -------------------------------------------------------------------------
    // get() — errors
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_psr_not_found_for_unregistered_id(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->container->get('not-registered');
    }

    // -------------------------------------------------------------------------
    // boot() — immutability
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_registering_after_boot(): void
    {
        $this->container->boot();

        $this->expectException(WpAgentException::class);
        $this->expectExceptionMessage('Cannot register');

        $this->container->bind('late', fn ($c) => 'too late');
    }

    /** @test */
    public function it_still_resolves_after_boot(): void
    {
        $this->container->bind('foo', fn ($c) => 'bar');
        $this->container->boot();

        self::assertSame('bar', $this->container->get('foo'));
    }

    // -------------------------------------------------------------------------
    // Dependency injection between bindings
    // -------------------------------------------------------------------------

    /** @test */
    public function it_injects_dependencies_via_container(): void
    {
        $this->container->singleton('dep', fn ($c) => new \stdClass());
        $this->container->singleton('parent', fn ($c) => [
            'dep' => $c->get('dep'),
        ]);

        $result = $this->container->get('parent');

        self::assertInstanceOf(\stdClass::class, $result['dep']);
    }
}
