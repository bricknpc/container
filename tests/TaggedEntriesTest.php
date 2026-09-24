<?php

declare(strict_types=1);

namespace Dirthara\Container\Tests;

use Iterator;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Dirthara\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Container\Tests\Fixtures\Plain;
use Dirthara\Container\Tests\Fixtures\Service;
use Dirthara\Container\Tests\Fixtures\TaggedHandler;
use Dirthara\Container\Tests\Fixtures\CollectsHandlers;
use Dirthara\Container\Exception\EntryNotFoundException;
use Dirthara\Container\Tests\Fixtures\OtherTaggedHandler;
use Dirthara\Container\Exception\ContainerLockedException;
use Dirthara\Container\Exception\InvalidAttributeException;
use Dirthara\Container\Tests\Fixtures\ServiceImplementation;

use function array_keys;
use function iterator_to_array;

final class TaggedEntriesTest extends TestCase
{
    #[Test]
    public function it_resolves_tagged_entries_in_the_order_they_were_tagged_keyed_by_identifier(): void
    {
        $container = new Container()
            ->bind(Service::class, ServiceImplementation::class)
            ->instance('123', 'numeric')
            ->tag([Service::class, Plain::class], 'group')
            ->tag('123', 'group')
            ->tag(Service::class, 'group');

        $identifiers = [];
        $entries = [];

        // @mago-expect analysis:mixed-assignment -- a tagged entry can be any value, which the assertions narrow
        foreach ($container->tagged('group') as $identifier => $entry) {
            $identifiers[] = $identifier;
            $entries[] = $entry;
        }

        self::assertSame([Service::class, Plain::class, '123'], $identifiers);
        self::assertInstanceOf(ServiceImplementation::class, $entries[0]);
        self::assertInstanceOf(Plain::class, $entries[1]);
        self::assertSame('numeric', $entries[2]);
    }

    #[Test]
    public function it_returns_nothing_for_an_unknown_tag(): void
    {
        self::assertSame([], iterator_to_array(new Container()->tagged('unknown')));
    }

    #[Test]
    public function it_resolves_each_entry_only_when_it_is_reached_and_again_on_each_iteration(): void
    {
        $container = new Container()->tag([Plain::class, 'missing'], 'group');
        $entries = $container->tagged('group');

        self::assertInstanceOf(IteratorAggregate::class, $entries);
        $iterator = $entries->getIterator();
        self::assertInstanceOf(Iterator::class, $iterator);
        self::assertInstanceOf(Plain::class, $iterator->current());

        $this->expectException(EntryNotFoundException::class);

        iterator_to_array($entries);
    }

    #[Test]
    public function it_includes_entries_tagged_after_the_collection_was_created(): void
    {
        $container = new Container();
        $entries = $container->tagged('group');

        $container->tag(Plain::class, 'group');

        self::assertSame([Plain::class], array_keys(iterator_to_array($entries)));
    }

    #[Test]
    public function it_tags_classes_by_their_tag_attributes(): void
    {
        $container = new Container()->tagByAttribute(TaggedHandler::class, OtherTaggedHandler::class, Plain::class);

        self::assertSame(
            [TaggedHandler::class, OtherTaggedHandler::class],
            array_keys(iterator_to_array($container->tagged('handlers'))),
        );
        self::assertSame([TaggedHandler::class], array_keys(iterator_to_array($container->tagged('listeners'))));
    }

    #[Test]
    public function it_refuses_to_read_the_attributes_of_an_unknown_class(): void
    {
        try {
            new Container()->tagByAttribute('Missing\\Handler');
            self::fail('Expected an InvalidAttributeException.');
        } catch (InvalidAttributeException $exception) {
            self::assertSame(['class' => 'Missing\\Handler'], $exception->context);
        }
    }

    #[Test]
    public function it_injects_the_tagged_entries_into_a_parameter_with_the_tagged_attribute(): void
    {
        $container = new Container()->tagByAttribute(TaggedHandler::class, OtherTaggedHandler::class);

        $collects = $container->get(CollectsHandlers::class);

        self::assertSame(
            [TaggedHandler::class, OtherTaggedHandler::class],
            array_keys(iterator_to_array($collects->handlers)),
        );
    }

    #[Test]
    public function it_prefers_a_contextual_binding_over_the_tagged_attribute(): void
    {
        $container = new Container()->tag(Plain::class, 'other');
        $container->when(CollectsHandlers::class)->needs('$handlers')->giveValue($container->tagged('other'));

        $collects = $container->get(CollectsHandlers::class);

        self::assertSame([Plain::class], array_keys(iterator_to_array($collects->handlers)));
    }

    #[Test]
    public function it_refuses_to_tag_once_it_is_locked(): void
    {
        $container = new Container();
        $container->lock();

        try {
            $container->tag(Plain::class, 'group');
            self::fail('Expected a ContainerLockedException.');
        } catch (ContainerLockedException $exception) {
            self::assertSame(['method' => 'tag'], $exception->context);
        }

        $this->expectException(ContainerLockedException::class);

        $container->tagByAttribute(TaggedHandler::class);
    }
}
