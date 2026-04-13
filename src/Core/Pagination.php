<?php

namespace App\Core;

/**
 * Simple pagination helper.
 */
class Pagination
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $totalPages;
    public int $offset;

    public function __construct(int $total, int $page = 1, int $perPage = 25)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->totalPages = max(1, (int) ceil($this->total / $this->perPage));
        $this->page = max(1, min($page, $this->totalPages));
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    /**
     * Create from current request (?page=X).
     */
    public static function fromRequest(int $total, int $perPage = 25): self
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        return new self($total, $page, $perPage);
    }

    /**
     * Build URL with page parameter, preserving other query params.
     */
    public function url(int $page): string
    {
        $params = $_GET;
        $params['page'] = $page;
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $path . '?' . http_build_query($params);
    }

    /**
     * Get visible page numbers for navigation (with ellipsis gaps).
     * Returns array of ints and '...' strings.
     */
    public function visiblePages(int $window = 2): array
    {
        if ($this->totalPages <= 7) {
            return range(1, $this->totalPages);
        }

        $pages = [1];

        $start = max(2, $this->page - $window);
        $end = min($this->totalPages - 1, $this->page + $window);

        if ($start > 2) $pages[] = '...';
        for ($i = $start; $i <= $end; $i++) $pages[] = $i;
        if ($end < $this->totalPages - 1) $pages[] = '...';

        $pages[] = $this->totalPages;

        return $pages;
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }
}
