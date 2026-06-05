<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\MysqliStore;

final class Repository
{
    public MysqliStore $movies;
    public MysqliStore $tv;
    public MysqliStore $people;

    public function __construct()
    {
        $this->movies = new MysqliStore('movies');
        $this->tv = new MysqliStore('tv');
        $this->people = new MysqliStore('people');
    }

    public function bySlug(string $type, string $slug): ?array
    {
        return $this->store($type)->findBy('slug', $slug);
    }

    public function store(string $type): MysqliStore
    {
        return match ($type) {
            'movie', 'movies' => $this->movies,
            'tv' => $this->tv,
            'person', 'people', 'actors' => $this->people,
            default => throw new \InvalidArgumentException('Unknown type: ' . $type),
        };
    }
}
