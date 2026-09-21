<?php

namespace App\Analysis;

/**
 * Everything an analyzer needs to know about the audited page.
 */
final class AuditContext
{
    private ?HtmlDocument $document = null;

    public function __construct(
        public readonly int $auditId,
        public readonly PageSnapshot $page,
    ) {}

    /**
     * Parsed once per job and reused by the analyzer.
     */
    public function document(): HtmlDocument
    {
        return $this->document ??= new HtmlDocument($this->page->html);
    }
}
