<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Doctrine\Rector\Class_\ClassAnnotationToNamedArgumentConstructorRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Api',
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/test',
    ]);

    // register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->rule(ClassAnnotationToNamedArgumentConstructorRector::class);

    $rectorConfig->ruleWithConfiguration(
        AnnotationToAttributeRector::class,
        [
            new AnnotationToAttribute('Orderadmin\\Application\\Mapping\\Annotation\\Cached'),
            new AnnotationToAttribute('Orderadmin\\Application\\Mapping\\Annotation\\DeHashed'),
            new AnnotationToAttribute('Orderadmin\\Application\\Mapping\\Annotation\\FullTextSearch'),
            new AnnotationToAttribute('Orderadmin\\Application\\Mapping\\Annotation\\Loggable'),
            new AnnotationToAttribute('Orderadmin\\Application\\Mapping\\Annotation\\Versioned'),
            new AnnotationToAttribute('Orderadmin\\Application\\Mapping\\Annotation\\VersionedV2Root'),
        ]
    );

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
        SetList::DEAD_CODE,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        SensiolabsSetList::FRAMEWORK_EXTRA_61,
    ]);
};
