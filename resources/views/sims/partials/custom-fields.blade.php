{{--
    Custom fields for SIM cards: the fieldset of the SIM's plan, or the
    default SIM fieldset. Mirrors models/custom_fields_form.blade.php,
    but reads values from Sim::customFieldValue() (JSON storage) and gates
    encrypted fields on the SIM viewSecrets policy instead of the asset one.
    Encrypted fields the user can't see are rendered without a name so they
    are never submitted (and therefore never overwritten).
--}}
@php
    $simFields = $item->customFields();
    $canViewSecrets = Gate::allows('viewSecrets', $item->exists ? $item : \App\Models\Sim::class);
@endphp

@if ($simFields->isNotEmpty())
    <fieldset name="custom-fields">
        <x-form.legend help_text="{!! trans('admin/custom_fields/general.general_help_text') !!}">
            {{ trans('admin/custom_fields/general.custom_fields') }}
        </x-form.legend>

        @foreach ($simFields as $field)
            @php
                $column = $field->db_column;
                $required = ($field->pivot->required == '1');
                $locked = ($field->field_encrypted == '1') && ! $canViewSecrets;
                $value = $locked ? null : old($column, $item->customFieldValue($field));
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
            @endphp

            <div class="form-group{{ $errors->has($column) ? ' has-error' : '' }}">
                <label for="{{ $column }}" class="col-md-3 control-label">
                    @if ($field->field_encrypted)
                        <x-icon type="locked" data-tooltip="true" data-placement="top" title="{{ trans('admin/custom_fields/general.value_encrypted') }}" />
                    @endif
                    {{ $field->name }}
                </label>

                <div class="col-md-7 col-sm-12">
                    @if ($locked)
                        <input type="text" value="{{ strtoupper(trans('admin/custom_fields/general.encrypted')) }}" class="form-control disabled" disabled aria-label="{{ $field->name }}">

                    @elseif ($field->element == 'listbox')
                        <x-input.select
                            :name="$column"
                            :id="$column"
                            :options="$field->formatFieldValuesAsArray()"
                            :selected="$value"
                            :required="$required"
                            class="format form-control"
                            style="width: 100%;"
                        />

                    @elseif (in_array($field->element, ['textarea', 'markdown-textarea']))
                        <textarea rows="5" class="form-control" id="{{ $column }}" name="{{ $column }}" @required($required)>{{ $value }}</textarea>
                        @if ($field->element == 'markdown-textarea')
                            <p class="help-block"><i class="fab fa-markdown" aria-hidden="true"></i> {{ trans('general.markdown') }}</p>
                        @endif

                    @elseif ($field->element == 'checkbox')
                        @php $checked = array_map('trim', explode(',', (string) $value)); @endphp
                        @foreach ($field->formatFieldValuesAsArray() as $optionValue)
                            <div>
                                <label class="form-control">
                                    <input type="checkbox" value="{{ $optionValue }}" name="{{ $column }}[]" @checked(in_array($optionValue, $checked))>
                                    {{ $optionValue }}
                                </label>
                            </div>
                        @endforeach

                    @elseif ($field->element == 'radio')
                        @foreach ($field->formatFieldValuesAsArray() as $optionValue)
                            <div>
                                <label class="form-control">
                                    <input type="radio" value="{{ $optionValue }}" name="{{ $column }}" @checked($value == $optionValue)>
                                    {{ $optionValue }}
                                </label>
                            </div>
                        @endforeach

                    @elseif (($field->element == 'date_picker') || ($field->format == 'DATE'))
                        <div class="input-group col-md-5" style="padding-left: 0px;">
                            <x-input.datepicker
                                id="{{ $column }}"
                                name="{{ $column }}"
                                :value="$value"
                                required="{{ $required ? '1' : '' }}"
                            />
                        </div>

                    @elseif (($field->element == 'datetime_picker') || ($field->format == 'DATETIME'))
                        <div class="input-group col-md-6" style="padding-left: 0px;">
                            <x-input.datetimepicker
                                id="{{ $column }}"
                                name="{{ $column }}"
                                :value="$value"
                                required="{{ $required ? '1' : '' }}"
                                :default_now="false"
                            />
                        </div>

                    @else
                        <input type="text" value="{{ $value }}" id="{{ $column }}" class="form-control" name="{{ $column }}" maxlength="191" @required($required)>
                    @endif

                    @if ($field->help_text != '')
                        <p class="help-block">{{ $field->help_text }}</p>
                    @endif

                    <x-form.error :name="$column" />
                </div>
            </div>
        @endforeach
    </fieldset>
@endif
