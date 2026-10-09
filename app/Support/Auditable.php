<?php

namespace App\Support;

/**
 * Records created/updated/deleted events with before/after values.
 *
 * Models may define:
 *  - protected array $auditRedact: attributes whose values are replaced by "[redacted]".
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            Audit::record($model->getMorphClass().'.created', $model, ['after' => $model->auditValues($model->getAttributes())]);
        });

        static::updated(function (self $model) {
            $before = [];
            $after = [];
            foreach (array_keys($model->getChanges()) as $key) {
                if (in_array($key, Audit::NEVER_LOG, true)) {
                    continue;
                }
                $before[$key] = $model->getOriginal($key);
                $after[$key] = $model->getAttribute($key);
            }

            if ($after !== []) {
                Audit::record($model->getMorphClass().'.updated', $model, [
                    'before' => $model->auditValues($before),
                    'after' => $model->auditValues($after),
                ]);
            }
        });

        static::deleted(function (self $model) {
            Audit::record($model->getMorphClass().'.deleted', $model, ['before' => $model->auditValues($model->getAttributes())]);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        $redact = property_exists($this, 'auditRedact') ? $this->auditRedact : [];
        $clean = [];

        foreach ($values as $key => $value) {
            if (in_array($key, Audit::NEVER_LOG, true)) {
                continue;
            }
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $clean[$key] = in_array($key, $redact, true) && $value !== null ? '[redacted]' : $value;
        }

        return $clean;
    }
}
