<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-03-04
 * Created Time: 09:51
 */

namespace STS\Bref\Bridge\Lambda\Queue;

use Illuminate\Queue\Jobs\SqsJob;

class Job extends SqsJob
{
    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        $this->deleted = true;
    }
}
