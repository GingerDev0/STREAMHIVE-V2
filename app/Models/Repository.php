<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\LiveCatalog;

final class Repository
{
    public LiveCatalog $movies;
    public LiveCatalog $tv;
    public LiveCatalog $people;

    public function __construct()
    {
        $this->movies = new LiveCatalog('movies');
        $this->tv = new LiveCatalog('tv');
        $this->people = new LiveCatalog('people');
    }

    public function bySlug(string $type, string $slug): ?array
    {
        return $this->store($type)->findBy('slug', $slug);
    }

    public function store(string $type): LiveCatalog
    {
        return match ($type) {
            'movie', 'movies' => $this->movies,
            'tv' => $this->tv,
            'person', 'people', 'actors' => $this->people,
            default => throw new \InvalidArgumentException('Unknown type: ' . $type),
        };
    }
}
