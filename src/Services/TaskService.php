<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AppException;
use App\Repositories\TaskRepositoryInterface;

/** Business rules of the timeline: valid names, 5-minute steps, inside the day, no overlaps. */
final class TaskService
{
    public const DAY_START = 4 * 60;   // 04:00
    public const DAY_END   = 24 * 60;  // 24:00
    public const SNAP      = 5;        // minutes

    private const NAME_MAX         = 60;
    private const DEFAULT_DURATION = 25;
    private const STATUSES         = ['unscheduled', 'scheduled', 'done'];

    public function __construct(private readonly TaskRepositoryInterface $tasks)
    {
    }

    public function list(): array
    {
        return $this->tasks->all();
    }

    /** Creates a task and puts it in the first free slot at or after `from_minute`. */
    public function create(array $input): array
    {
        $name = $this->validName($input['name'] ?? null);
        $duration = $this->validDuration($input['duration'] ?? self::DEFAULT_DURATION);
        $from = isset($input['from_minute'])
            ? $this->intValue($input['from_minute'], 'Start time')
            : self::DAY_START;

        $start = $this->findFreeSlot($duration, $from);

        return $this->tasks->create($name, $duration, $start, $start === null ? 'unscheduled' : 'scheduled');
    }

    /** Applies any of: name, duration, start, status. */
    public function update(int $id, array $input): array
    {
        $task = $this->tasks->find($id) ?? throw new AppException('That box no longer exists.', 404);
        $next = $task;

        if (array_key_exists('name', $input)) {
            $next['name'] = $this->validName($input['name']);
        }
        if (array_key_exists('duration', $input)) {
            $next['duration'] = $this->validDuration($input['duration']);
        }
        if (array_key_exists('start', $input)) {
            $next['start'] = $input['start'] === null ? null : $this->validStart($input['start']);
        }
        if (array_key_exists('status', $input)) {
            $next['status'] = $this->validStatus($input['status']);
        }

        $next = $this->normaliseStatus($next);

        $placementChanged = $next['start'] !== $task['start'] || $next['duration'] !== $task['duration'];
        if ($next['start'] !== null && $placementChanged) {
            $this->assertFits($next['start'], $next['duration'], $id);
        }

        $this->tasks->update($id, [
            'name'     => $next['name'],
            'duration' => $next['duration'],
            'start'    => $next['start'],
            'status'   => $next['status'],
        ]);

        return $next;
    }

    public function delete(int $id): void
    {
        if (!$this->tasks->delete($id)) {
            throw new AppException('That box no longer exists.', 404);
        }
    }

    // ---------- rules ----------

    /** A box without a start time is unscheduled; a box with one can't be "unscheduled". */
    private function normaliseStatus(array $task): array
    {
        if ($task['start'] === null) {
            if ($task['status'] === 'done') {
                throw new AppException('Put this box on the timeline before marking it done.');
            }
            $task['status'] = 'unscheduled';
        } elseif ($task['status'] === 'unscheduled') {
            $task['status'] = 'scheduled';
        }

        return $task;
    }

    private function assertFits(int $start, int $duration, int $ignoreId): void
    {
        if ($start < self::DAY_START || $start + $duration > self::DAY_END) {
            throw new AppException(sprintf(
                'A box has to fit inside the day (%02d:00–%02d:00).',
                intdiv(self::DAY_START, 60),
                intdiv(self::DAY_END, 60)
            ));
        }
        if ($this->firstClash($start, $duration, $this->tasks->scheduled($ignoreId)) !== null) {
            throw new AppException('That time is already boxed.');
        }
    }

    private function findFreeSlot(int $duration, int $from): ?int
    {
        $others = $this->tasks->scheduled();
        $candidate = $this->snap(max(self::DAY_START, min($from, self::DAY_END - $duration)));

        while ($candidate + $duration <= self::DAY_END) {
            $clash = $this->firstClash($candidate, $duration, $others);
            if ($clash === null) {
                return $candidate;
            }
            $candidate = $this->snap($clash['start'] + $clash['duration']);
        }

        return null;
    }

    private function firstClash(int $start, int $duration, array $others): ?array
    {
        foreach ($others as $other) {
            if ($start < $other['start'] + $other['duration'] && $start + $duration > $other['start']) {
                return $other;
            }
        }

        return null;
    }

    private function snap(int $minutes): int
    {
        return (int) (round($minutes / self::SNAP) * self::SNAP);
    }

    // ---------- input validation ----------

    private function validName(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '') {
            throw new AppException('Give the box a name.');
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            throw new AppException('Keep the name under ' . self::NAME_MAX . ' characters.');
        }

        return $name;
    }

    private function validDuration(mixed $value): int
    {
        $duration = $this->intValue($value, 'Duration');
        $max = self::DAY_END - self::DAY_START;
        if ($duration < self::SNAP || $duration > $max || $duration % self::SNAP !== 0) {
            throw new AppException('Duration must be in steps of ' . self::SNAP . ' minutes, up to ' . intdiv($max, 60) . ' hours.');
        }

        return $duration;
    }

    private function validStart(mixed $value): int
    {
        $start = $this->intValue($value, 'Start time');
        if ($start % self::SNAP !== 0) {
            throw new AppException('Start time must be in steps of ' . self::SNAP . ' minutes.');
        }

        return $start;
    }

    private function validStatus(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::STATUSES, true)) {
            throw new AppException('Unknown status.');
        }

        return $value;
    }

    private function intValue(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        throw new AppException($label . ' must be a whole number.');
    }
}
