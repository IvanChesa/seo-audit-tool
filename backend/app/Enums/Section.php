<?php

namespace App\Enums;

use App\Analysis\Analyzers\ContentAnalyzer;
use App\Analysis\Analyzers\HeadingsAnalyzer;
use App\Analysis\Analyzers\LinksAnalyzer;
use App\Analysis\Analyzers\MetaAnalyzer;
use App\Analysis\Analyzers\PerformanceAnalyzer;
use App\Analysis\Analyzers\TechnicalAnalyzer;
use App\Analysis\Contracts\Analyzer;

/**
 * The report is split in sections; each one is produced by an analyzer that
 * runs as its own queued job. Weights add up to 100 and are documented in
 * the README ("Cómo se calcula la puntuación").
 */
enum Section: string
{
    case Technical = 'technical';
    case Meta = 'meta';
    case Headings = 'headings';
    case Content = 'content';
    case Links = 'links';
    case Performance = 'performance';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Técnico',
            self::Meta => 'Metadatos',
            self::Headings => 'Encabezados',
            self::Content => 'Contenido',
            self::Links => 'Enlaces',
            self::Performance => 'Rendimiento',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Technical => 25,
            self::Meta => 20,
            self::Headings => 15,
            self::Content => 15,
            self::Links => 15,
            self::Performance => 10,
        };
    }

    /**
     * @return class-string<Analyzer>
     */
    public function analyzer(): string
    {
        return match ($this) {
            self::Technical => TechnicalAnalyzer::class,
            self::Meta => MetaAnalyzer::class,
            self::Headings => HeadingsAnalyzer::class,
            self::Content => ContentAnalyzer::class,
            self::Links => LinksAnalyzer::class,
            self::Performance => PerformanceAnalyzer::class,
        };
    }

    /**
     * Maximum run time of the job, in seconds. Network-bound sections get
     * more time. Must stay below the queue's retry_after (300 s).
     */
    public function timeout(): int
    {
        return match ($this) {
            self::Links => 150,
            self::Performance => 120,
            self::Technical => 60,
            default => 30,
        };
    }
}
