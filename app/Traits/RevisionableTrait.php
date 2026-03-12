<?php

namespace App\Traits;

use App\Models\Revision;
use App\Models\Revisionable;
use App\Models\RevisionData;
use DateTime;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class RevisionableTrait
 */
trait RevisionableTrait
{
    /**
     * Keeps the list of values that have been updated
     *
     * @var array
     */
    protected $dirtyData = [];

    /**
     * @var array
     */
    private $originalData = [];

    /**
     * @var array
     */
    private $updatedData = [];

    /**
     * @var bool
     */
    private $updating = false;

    /**
     * @var array
     */
    private $dontKeep = [];

    /**
     * @var array
     */
    private $doKeep = [];

    /**
     * Ensure that the bootRevisionableTrait is called only
     * if the current installation is a laravel 4 installation
     * Laravel 5 will call bootRevisionableTrait() automatically
     */
    public static function boot(): void
    {
        parent::boot();

        if (! method_exists(static::class, 'bootTraits')) {
            static::bootRevisionableTrait();
        }
    }

    /**
     * Create the event listeners for the saving and saved events
     * This lets us save revisions whenever a save is made, no matter the
     * http method.
     */
    public static function bootRevisionableTrait(): void
    {
        static::saving(function ($model): void {
            $model->preSave();
        });

        static::saved(function ($model): void {
            $model->postSave();
        });

        static::created(function ($model): void {
            $model->postCreate();
        });

        static::deleted(function ($model): void {
            $model->preSave();
            $model->postDelete();
            $model->postForceDelete();
        });
    }

    /**
     * @return mixed
     */
    public function revisionHistory()
    {
        return $this->morphMany(Revision::class, 'revisionable')->orderBy('id', 'desc');
    }

    /**
     * @return mixed
     */
    public function revisionHistoryOldestRecord()
    {
        return $this->morphMany(Revision::class, 'revisionable');
    }

    /**
     * Generates a list of the last $limit revisions made to any objects of the class it is being called from.
     *
     * @param  int  $limit
     * @param  string  $order
     * @return mixed
     */
    public static function classRevisionHistory($limit = 100, $order = 'desc')
    {
        $model = Revisionable::newModel();

        return $model->where('revisionable_type', static::class)
            ->orderBy('updated_at', $order)->limit($limit)->get();
    }

    /**
     * Invoked before a model is saved. Return false to abort the operation.
     */
    public function preSave(): void
    {
        if (! isset($this->revisionEnabled) || $this->revisionEnabled) {
            // if there's no revisionEnabled. Or if there is, if it's true

            $this->originalData = $this->original;
            $this->updatedData = $this->attributes;

            // we can only safely compare basic items,
            // so for now we drop any object based items, like DateTime
            foreach ($this->updatedData as $key => $val) {
                $castCheck = ['object', 'array'];
                if (isset($this->casts[$key]) && in_array(gettype($val), $castCheck) && in_array($this->casts[$key], $castCheck) && isset($this->originalData[$key])) {
                    // Sorts the keys of a JSON object due Normalization performed by MySQL
                    // So it doesn't set false flag if it is changed only order of key or whitespace after comma

                    $updatedData = $this->sortJsonKeys(json_decode((string) $this->updatedData[$key], true));

                    $this->updatedData[$key] = json_encode($updatedData);
                    $this->originalData[$key] = json_encode(json_decode((string) $this->originalData[$key], true));
                } elseif (gettype($val) === 'object' && ! method_exists($val, '__toString')) {
                    unset($this->originalData[$key]);
                    unset($this->updatedData[$key]);
                    $this->dontKeep[] = $key;
                }
            }

            // the below is ugly, for sure, but it's required so we can save the standard model
            // then use the keep / dontkeep values for later, in the isRevisionable method
            $this->dontKeep = isset($this->dontKeepRevisionOf) ?
                array_merge($this->dontKeepRevisionOf, $this->dontKeep)
                : $this->dontKeep;

            $this->doKeep = isset($this->keepRevisionOf) ?
                array_merge($this->keepRevisionOf, $this->doKeep)
                : $this->doKeep;

            unset($this->attributes['dontKeepRevisionOf']);
            unset($this->attributes['keepRevisionOf']);

            $this->dirtyData = $this->getDirty();
            $this->updating = $this->exists;
        }
    }

    /**
     * Called after a model is successfully saved.
     */
    public function postSave(): void
    {
        $LimitReached = isset($this->historyLimit) && $this->revisionHistory()->count() >= $this->historyLimit;

        $RevisionCleanup = $this->revisionCleanup ?? false;
        // check if the model already exists
        if (((! isset($this->revisionEnabled) || $this->revisionEnabled) && $this->updating) && (! $LimitReached || $RevisionCleanup)) {
            // if it does, it means we're updating

            $changes_to_record = $this->changedRevisionableFields();

            $revisionableType = $this->getMorphClass();
            $revisionableId = $this->getKey();

            $revisions = [];
            foreach ($changes_to_record as $key => $change) {
                $hasPreviousForField = RevisionData::query()
                    ->where('field_key', $key)
                    ->whereHas('revision', function ($query) use ($revisionableType, $revisionableId): void {
                        $query
                            ->where('revisionable_type', $revisionableType)
                            ->where('revisionable_id', $revisionableId);
                    })
                    ->exists();

                $original = [
                    'field_key' => $key,
                    // Store baseline old_value only for the first revision of this field.
                    // Subsequent revisions store only new_value to reduce storage.
                    'old_value' => $hasPreviousForField ? null : Arr::get($this->originalData, $key),
                    'new_value' => $this->updatedData[$key],
                    'created_by' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                    'updated_by' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                    'created_at' => new DateTime,
                    'updated_at' => new DateTime,
                ];

                $revisions[] = array_merge($original, $this->getAdditionalFields());
            }

            if ($revisions !== []) {
                if ($LimitReached && $RevisionCleanup) {
                    $toDelete = $this->revisionHistoryOldestRecord()->orderBy('id', 'asc')->limit(1)->get();

                    if ($toDelete) {
                        $ids = $toDelete->pluck('id')->toArray();
                        RevisionData::query()->whereIn('revision_id', $ids)->delete();
                        Revision::query()->whereIn('id', $ids)->delete();
                    }
                }

                $revisioninput = [
                    'revisionable_type' => $this->getMorphClass(),
                    'revisionable_id' => $this->getKey(),
                    'created_by' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                    'updated_by' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                ];
                $revisionobj = Revision::query()->create($revisioninput);
                if ($revisionobj) {
                    $revision_id = $revisionobj->id;
                    foreach ($revisions as $key => $revision) {
                        $revisions[$key]['revision_id'] = $revision_id;
                    }

                    $revision = Revisionable::newModel();
                    // \DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    DB::table($revision->getTable())->insert($revisions);
                    // \DB::statement('SET FOREIGN_KEY_CHECKS=1');

                    // \Event::dispatch('revisionable.saved', array('model' => $this, 'revisions' => $revisions));
                }
            }
        }
    }

    /**
     * Called after record successfully created
     */
    public function postCreate(): ?bool
    {
        // Check if we should store creations in our revision history
        // Set this value to true in your model if you want to
        if (empty($this->revisionCreationsEnabled)) {
            // We should not store creations.
            return false;
        }

        if ((! isset($this->revisionEnabled) || $this->revisionEnabled)) {
            $revisions[] = [
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => self::CREATED_AT,
                'old_value' => null,
                'new_value' => $this->{self::CREATED_AT},
                'user_id' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                'created_at' => new DateTime,
                'updated_at' => new DateTime,
            ];

            // Determine if there are any additional fields we'd like to add to our model contained in the config file, and
            // get them into an array.
            $revisions = array_merge($revisions[0], $this->getAdditionalFields());

            $revision = Revisionable::newModel();
            // \DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($revision->getTable())->insert($revisions);
            // \DB::statement('SET FOREIGN_KEY_CHECKS=1');
            // \Event::dispatch('revisionable.created', array('model' => $this, 'revisions' => $revisions));
        }

        return null;
    }

    /**
     * If softdeletes are enabled, store the deleted time
     */
    public function postDelete(): void
    {
        if ((! isset($this->revisionEnabled) || $this->revisionEnabled)
            && $this->isSoftDelete()
            && $this->isRevisionable($this->getDeletedAtColumn())
        ) {
            $revisions[] = [
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => $this->getDeletedAtColumn(),
                'old_value' => null,
                'new_value' => $this->{$this->getDeletedAtColumn()},
                'user_id' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                'created_at' => new DateTime,
                'updated_at' => new DateTime,
            ];

            // Since there is only one revision because it's deleted, let's just merge into revision[0]
            $revisions = array_merge($revisions[0], $this->getAdditionalFields());

            $revision = Revisionable::newModel();
            // \DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($revision->getTable())->insert($revisions);
            // \DB::statement('SET FOREIGN_KEY_CHECKS=1');
            // \Event::dispatch('revisionable.deleted', array('model' => $this, 'revisions' => $revisions));
        }
    }

    /**
     * If forcedeletes are enabled, set the value created_at of model to null
     */
    public function postForceDelete(): ?bool
    {
        if (empty($this->revisionForceDeleteEnabled)) {
            return false;
        }

        if ((! isset($this->revisionEnabled) || $this->revisionEnabled)
            && (($this->isSoftDelete() && $this->isForceDeleting()) || ! $this->isSoftDelete())) {
            $revisions[] = [
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => self::CREATED_AT,
                'old_value' => $this->{self::CREATED_AT},
                'new_value' => null,
                'user_id' => (empty($this->getSystemUserId()) ? 1 : $this->getSystemUserId()),
                'created_at' => new DateTime,
                'updated_at' => new DateTime,
            ];

            $revision = Revisionable::newModel();
            // \DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($revision->getTable())->insert($revisions);
            // \DB::statement('SET FOREIGN_KEY_CHECKS=1');
            // \Event::dispatch('revisionable.deleted', array('model' => $this, 'revisions' => $revisions));
        }

        return null;
    }

    /**
     * Attempt to find the user id of the currently logged in user
     * Supports Cartalyst Sentry/Sentinel based authentication, as well as stock Auth
     */
    public function getSystemUserId()
    {
        try {
            if (class_exists($class = '\SleepingOwl\AdminAuth\Facades\AdminAuth')
                || class_exists($class = '\Cartalyst\Sentry\Facades\Laravel\Sentry')
                || class_exists($class = '\Cartalyst\Sentinel\Laravel\Facades\Sentinel')) {
                return $class::check() ? $class::getUser()->id : null;
            }

            if (function_exists('backpack_auth') && backpack_auth()->check()) {
                return backpack_user()->id;
            }

            if (Auth::check()) {
                return Auth::user()->getAuthIdentifier();
            }
        } catch (Exception) {
            return null;
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    public function getAdditionalFields(): array
    {
        $additional = [];
        // Determine if there are any additional fields we'd like to add to our model contained in the config file, and
        // get them into an array.
        $fields = config('revisionable.additional_fields', []);
        foreach ($fields as $field) {
            if (Arr::has($this->originalData, $field)) {
                $additional[$field] = Arr::get($this->originalData, $field);
            }
        }

        return $additional;
    }

    /**
     * @return mixed
     */
    public function getRevisionFormattedFields()
    {
        return $this->revisionFormattedFields;
    }

    /**
     * @return mixed
     */
    public function getRevisionFormattedFieldNames()
    {
        return $this->revisionFormattedFieldNames;
    }

    /**
     * Identifiable Name
     * When displaying revision history, when a foreign key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     *
     * @return string an identifying name for the model
     */
    public function identifiableName()
    {
        return $this->getKey();
    }

    /**
     * Revision Unknown String
     * When displaying revision history, when a foreign key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     *
     * @return string an identifying name for the model
     */
    public function getRevisionNullString(): string
    {
        return $this->revisionNullString ?? 'nothing';
    }

    /**
     * No revision string
     * When displaying revision history, if the revisions value
     * cant be figured out, this is used instead.
     * It can be overridden.
     *
     * @return string an identifying name for the model
     */
    public function getRevisionUnknownString(): string
    {
        return $this->revisionUnknownString ?? 'unknown';
    }

    /**
     * Disable a revisionable field temporarily
     * Need to do the adding to array longhanded, as there's a
     * PHP bug https://bugs.php.net/bug.php?id=42030
     *
     * @param  mixed  $field
     */
    public function disableRevisionField($field): void
    {
        if (! isset($this->dontKeepRevisionOf)) {
            $this->dontKeepRevisionOf = [];
        }

        if (is_array($field)) {
            foreach ($field as $one_field) {
                $this->disableRevisionField($one_field);
            }
        } else {
            $donts = $this->dontKeepRevisionOf;
            $donts[] = $field;
            $this->dontKeepRevisionOf = $donts;
            unset($donts);
        }
    }

    /**
     * Get all of the changes that have been made, that are also supposed
     * to have their changes recorded
     *
     * @return array fields with new data, that should be recorded
     */
    private function changedRevisionableFields(): array
    {
        $changes_to_record = [];
        foreach ($this->dirtyData as $key => $value) {
            // check that the field is revisionable, and double check
            // that it's actually new data in case dirty is, well, clean
            if ($this->isRevisionable($key) && ! is_array($value)) {
                if (! array_key_exists($key, $this->originalData) || $this->originalData[$key] !== $this->updatedData[$key]) {
                    $changes_to_record[$key] = $value;
                }
            } else {
                // we don't need these any more, and they could
                // contain a lot of data, so lets trash them.
                unset($this->updatedData[$key]);
                unset($this->originalData[$key]);
            }
        }

        return $changes_to_record;
    }

    /**
     * Check if this field should have a revision kept
     *
     * @param  string  $key
     * @return bool
     */
    private function isRevisionable($key)
    {
        // If the field is explicitly revisionable, then return true.
        // If it's explicitly not revisionable, return false.
        // Otherwise, if neither condition is met, only return true if
        // we aren't specifying revisionable fields.
        if (isset($this->doKeep) && in_array($key, $this->doKeep)) {
            return true;
        }

        if (isset($this->dontKeep) && in_array($key, $this->dontKeep)) {
            return false;
        }

        if (count($this->doKeep) === 0 && ! in_array($key, $this->dontKeep)) {
            return true;
        }

        return empty($this->doKeep);
    }

    /**
     * Check if soft deletes are currently enabled on this model
     *
     * @return bool
     */
    private function isSoftDelete()
    {
        // check flag variable used in laravel 4.2+
        if (isset($this->forceDeleting)) {
            return ! $this->forceDeleting;
        }

        return $this->softDelete ?? false;
    }

    /**
     * Sorts the keys of a JSON object
     *
     * Normalization performed by MySQL and
     * discards extra whitespace between keys, values, or elements
     * in the original JSON document.
     * To make lookups more efficient, it sorts the keys of a JSON object.
     *
     * @param  mixed  $attribute
     * @return mixed
     */
    private function sortJsonKeys($attribute)
    {
        if (empty($attribute)) {
            return $attribute;
        }

        foreach ($attribute as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = $this->sortJsonKeys($value);
            } else {
                continue;
            }

            ksort($value);
            $attribute[$key] = $value;
        }

        return $attribute;
    }
}
