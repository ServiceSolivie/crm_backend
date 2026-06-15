<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Base class for module-level resource collections.
 *
 * Pagination meta/links produced by Laravel are preserved and surfaced
 * under "meta"/"links" by App\Http\Responses\ApiResponse::success().
 */
abstract class BaseCollection extends ResourceCollection {}
