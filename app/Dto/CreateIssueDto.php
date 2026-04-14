<?php

declare(strict_types=1);

namespace App\Dto;

use Karhu\Attributes\In;
use Karhu\Attributes\Required;
use Karhu\Attributes\StringLength;

final class CreateIssueDto
{
    #[Required]
    #[StringLength(min: 3, max: 100)]
    public string $title = '';

    #[Required]
    #[StringLength(min: 10, message: 'Description must be at least 10 characters.')]
    public string $body = '';

    #[In(values: ['low', 'medium', 'high', 'critical'])]
    public string $priority = 'medium';
}
