<?php

namespace App\Models;

use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Str;

/**
 * Revision.
 *
 * Base model to allow for revision history on
 * any model that extends this model
 */
class RevisionData extends Eloquent
{
    use HasFactory;

    /**
     * @var string
     */
    public $table = 'revisions_data';

    /**
     * @var array
     */
    protected $revisionFormattedFields = [];

    public function revision()
    {
        return $this->belongsTo(Revision::class, 'revision_id');
    }

    /**
     * Field Name
     *
     * Returns the field that was updated, in the case that it's a foreign key
     * denoted by a suffix of "_id", then "_id" is simply stripped
     *
     * @return string field
     */
    public function fieldName(): string
    {
        $key = $this->revisionKey();
        $formatted = $this->formatFieldName($key);

        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }

        if (str_contains($key, '_id')) {
            return str_replace('_id', '', $key);
        }

        return $key;
    }

    /**
     * Old Value.
     *
     * Grab the old value of the field, if it was a foreign key
     * attempt to get an identifying name for the model.
     *
     * @return string old value
     */
    public function oldValue()
    {
        return $this->getValue('old');
    }

    /**
     * New Value.
     *
     * Grab the new value of the field, if it was a foreign key
     * attempt to get an identifying name for the model.
     *
     * @return string old value
     */
    public function newValue()
    {
        return $this->getValue('new');
    }

    /**
     * User Responsible.
     *
     * @return mixed
     */
    public function userResponsible()
    {
        if (empty($this->created_by)) {
            return false;
        }

        if (class_exists($class = '\Cartalyst\Sentry\Facades\Laravel\Sentry')
            || class_exists($class = '\Cartalyst\Sentinel\Laravel\Facades\Sentinel')
        ) {
            return $class::findUserById($this->created_by);
        }

        $user_model = resolve(Repository::class)->get('auth.model');
        if (empty($user_model)) {
            $user_model = resolve(Repository::class)->get('auth.providers.users.model');
            if (empty($user_model)) {
                return false;
            }
        }

        if (! class_exists($user_model)) {
            return false;
        }

        return $user_model::find($this->created_by);
    }

    /**
     * Returns the object we have the history of
     *
     * @return object|false
     */
    public function historyOf()
    {
        if (class_exists($class = $this->revisionableType())) {
            return $class::find($this->getAttribute('revisionable_id'));
        }

        return false;
    }

    /*
     * Examples:
    array(
        'public' => 'boolean:Yes|No',
        'minimum'  => 'string:Min: %s'
    )
     */
    /**
     * Format the value according to the $revisionFormattedFields array.
     *
     * @return string formatted value
     */
    public function format($key, $value)
    {
        $revisionableType = $this->revisionableType();
        if (! is_string($revisionableType) || $revisionableType === '') {
            return $value;
        }

        $related_model = $this->getActualClassNameForMorph($revisionableType);
        $related_model = new $related_model;

        $revisionFormattedFields = $related_model->getRevisionFormattedFields();

        if (isset($revisionFormattedFields[$key])) {
            $format = $revisionFormattedFields[$key];

            if (is_string($format) && str_contains($format, ':')) {
                [$type, $pattern] = explode(':', $format, 2);

                if ($type === 'boolean') {
                    [$truthy, $falsy] = array_pad(explode('|', $pattern, 2), 2, '');

                    return (string) ((bool) $value ? $truthy : $falsy);
                }

                if (str_contains($pattern, '%s')) {
                    return sprintf($pattern, (string) $value);
                }
            }

            if (is_string($format) && str_contains($format, '%s')) {
                return sprintf($format, (string) $value);
            }
        } else {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Format field name.
     *
     * Allow overrides for field names.
     */
    private function formatFieldName(string $key): ?string
    {
        $revisionableType = $this->revisionableType();
        if (! is_string($revisionableType) || $revisionableType === '') {
            return null;
        }

        $related_model = $this->getActualClassNameForMorph($revisionableType);
        $related_model = new $related_model;

        $revisionFormattedFieldNames = $related_model->getRevisionFormattedFieldNames();

        if (isset($revisionFormattedFieldNames[$key])) {
            $formatted = $revisionFormattedFieldNames[$key];

            return is_string($formatted) ? $formatted : null;
        }

        return null;
    }

    /**
     * Responsible for actually doing the grunt work for getting the
     * old or new value for the revision.
     *
     * @param  string  $which  old or new
     * @return string value
     */
    private function getValue(string $which = 'new')
    {
        $which_value = $which.'_value';
        $key = $this->revisionKey();

        // First find the main model that was updated
        $main_model = $this->revisionableType();
        // Load it, WITH the related model
        if (is_string($main_model) && class_exists($main_model)) {
            $main_model = new $main_model;

            try {
                if ($this->isRelated()) {
                    $related_model = $this->getRelatedModel();
                    // Now we can find out the namespace of of related model
                    if (! method_exists($main_model, $related_model)) {
                        $related_model = Str::camel($related_model);
                        // for cases like published_status_id
                        throw_unless(method_exists($main_model, $related_model), Exception::class, 'Relation '.$related_model.' does not exist for '.$main_model::class);
                    }

                    $related_class = $main_model->$related_model()->getRelated();

                    // Finally, now that we know the namespace of the related model
                    // we can load it, to find the information we so desire
                    $item = $related_class::find($this->$which_value);

                    if (is_null($this->$which_value) || $this->$which_value === '') {
                        $item = new $related_class;

                        return $item->getRevisionNullString();
                    }

                    if (! $item) {
                        $item = new $related_class;

                        return $this->format($key, $item->getRevisionUnknownString());
                    }

                    // Check if model use RevisionableTrait
                    if (method_exists($item, 'identifiableName')) {
                        // see if there's an available mutator
                        $mutator = 'get'.Str::studly($key).'Attribute';
                        if (method_exists($item, $mutator)) {
                            return $this->format($item->$mutator($key), $item->identifiableName());
                        }

                        return $this->format($key, $item->identifiableName());
                    }
                }
            } catch (Exception) {
                // Just a fail-safe, in the case the data setup isn't as expected
                // Nothing to do here.
            }

            // if there was an issue
            // or, if it's a normal value

            $mutator = 'get'.Str::studly($key).'Attribute';
            if (method_exists($main_model, $mutator)) {
                return $this->format($key, $main_model->$mutator($this->$which_value));
            }
        }

        return $this->format($key, $this->$which_value);
    }

    /**
     * Return true if the key is for a related model.
     *
     * @return bool
     */
    private function isRelated()
    {
        $isRelated = false;
        $idSuffix = '_id';
        $key = $this->revisionKey();
        $pos = strrpos($key, $idSuffix);

        if ($pos !== false
            && strlen($key) - strlen($idSuffix) === $pos) {
            return true;
        }

        return $isRelated;
    }

    /**
     * Return the name of the related model.
     */
    private function getRelatedModel(): string
    {
        $idSuffix = '_id';
        $key = $this->revisionKey();

        return substr($key, 0, strlen($key) - strlen($idSuffix));
    }

    private function revisionKey(): string
    {
        return (string) ($this->getAttribute('key') ?? '');
    }

    private function revisionableType(): ?string
    {
        $type = $this->getAttribute('revisionable_type');

        return is_string($type) && $type !== '' ? $type : null;
    }
}
