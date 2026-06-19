<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;

interface ProfileProvider
{
    /**
     * Fetch profile data by username.
     *
     * @return array{
     *     username: string,
     *     followers_count: int,
     *     following_count: int,
     *     posts_count: int,
     *     bio: ?string,
     *     profile_picture_url: ?string
     * }
     *
     * @throws FatalProfileException
     * @throws RetriableProfileException
     */
    public function fetchProfile(string $username): array;
}
