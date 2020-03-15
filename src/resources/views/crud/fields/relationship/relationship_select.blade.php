@php

    //in case entity is superNews we want the url friendly super-news
    $connected_entity = new $field['model'];
    $connected_entity_key_name = $connected_entity->getKeyName();

    $field['multiple'] = $field['multiple'] ?? $crud->relationAllowsMultiple($field['relation_type']);
    $field['attribute'] = $field['attribute'] ?? $connected_entity->getIdentifiableName();
    $field['allows_null'] = $field['allows_null'] ?? $crud->model::isColumnNullable($field['name']);
    // Note: isColumnNullable returns true if column is nullable in database, also true if column does not exist.


    if (!isset($field['options'])) {
            $field['options'] = $connected_entity::all()->pluck($field['attribute'],$connected_entity_key_name);
        } else {
            $field['options'] = call_user_func($field['options'], $field['model']::query()->pluck($field['attribute'],$connected_entity_key_name));
    }

    // make sure the $field['value'] takes the proper value
    // and format it to JSON, so that select2 can parse it
    $current_value = old(square_brackets_to_dots($field['name'])) ?? $field['value'] ?? $field['default'] ?? '';

    if ($current_value != false) {
        switch (gettype($current_value)) {
            case 'array':
                $current_value = $connected_entity
                                    ->whereIn($connected_entity_key_name, $current_value)
                                    ->pluck($field['attribute'], $connected_entity_key_name);
                break;

            case 'object':
                    $current_value = $current_value
                                    ->pluck($field['attribute'], $connected_entity_key_name)
                                    ->toArray();
                break;

            default:
                $current_value = $connected_entity
                                ->where($connected_entity_key_name, $current_value)
                                ->pluck($field['attribute'], $connected_entity_key_name);
                break;
        }
    }

    $field['value'] = json_encode($current_value);
@endphp

<div @include('crud::inc.field_wrapper_attributes') >
    <label>{!! $field['label'] !!}</label>

    <select
        style="width:100%"
        name="{{ $field['name'].($field['multiple']?'[]':'') }}"
        data-init-function="bpFieldInitRelationshipSelectElement"
        data-column-nullable="{{ var_export($field['allows_null']) }}"
        data-dependencies="{{ isset($field['dependencies'])?json_encode(array_wrap($field['dependencies'])): json_encode([]) }}"
        data-model-local-key="{{$crud->model->getKeyName()}}"
        data-placeholder="{{ $field['placeholder'] }}"
        data-field-attribute="{{ $field['attribute'] }}"
        data-connected-entity-key-name="{{ $connected_entity_key_name }}"
        data-include-all-form-fields="{{ $field['include_all_form_fields'] ?? 'true' }}"
        data-current-value="{{ $field['value'] }}"
        data-field-multiple="{{var_export($field['multiple'])}}"
        data-options-for-select="{{json_encode($field['options'])}}"

        @include('crud::inc.field_attributes', ['default_class' =>  'form-control'])

        @if($field['multiple'])
        multiple
        @endif
        >
    </select>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
</div>

{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}
@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    {{-- FIELD CSS - will be loaded in the after_styles section --}}
    @push('crud_fields_styles')
    <!-- include select2 css-->
    <link href="{{ asset('packages/select2/dist/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />

    @endpush

    {{-- FIELD JS - will be loaded in the after_scripts section --}}
    @push('crud_fields_scripts')
    <!-- include select2 js-->
    <script src="{{ asset('packages/select2/dist/js/select2.full.min.js') }}"></script>
    @if (app()->getLocale() !== 'en')
    <script src="{{ asset('packages/select2/dist/js/i18n/' . app()->getLocale() . '.js') }}"></script>
    @endif
    @endpush



<!-- include field specific select2 js-->
@push('crud_fields_scripts')
<script>
    // if nullable, make sure the Clear button uses the translated string
    document.styleSheets[0].addRule('.select2-selection__clear::after','content:  "{{ trans('backpack::crud.clear') }}";');


    /**
     *
     * This method gets called automatically by Backpack:
     *
     * @param  node element The jQuery-wrapped "select" element.
     * @return void
     */
    function bpFieldInitRelationshipSelectElement(element) {
        var form = element.closest('form');
        var $placeholder = element.attr('data-placeholder');
        var $modelKey = element.attr('data-model-local-key');
        var $fieldAttribute = element.attr('data-field-attribute');
        var $connectedEntityKeyName = element.attr('data-connected-entity-key-name');
        var $includeAllFormFields = element.attr('data-include-all-form-fields') == 'false' ? false : true;
        var $dependencies = JSON.parse(element.attr('data-dependencies'));
        var $multiple = element.attr('data-field-multiple')  == 'false' ? false : true;
        var $optionsForSelect = JSON.parse(element.attr('data-options-for-select'));
        var $allows_null = (element.attr('data-column-nullable') == 'true') ? true : false;
        var $allowClear = $allows_null;

        var $item = false;

        var $value = JSON.parse(element.attr('data-current-value'))

        if(Object.keys($value).length > 0) {
            $item = true;
        }


        var selectedOptions = [];
        var $currentValue = $item ? $value : '';


        for (const [key, value] of Object.entries($optionsForSelect)) {
            var $option = new Option(value, key);
            $(element).append($option);
            //if option key is the same of current value we reselect it
            if(!$multiple) {
                if (key == Object.keys($currentValue)[0]) {
                    $(element).val(key);
                }
            }else{
                for (const [key, value] of Object.entries($currentValue)) {
                        selectedOptions.push(key);
                    }

                    $(element).val(selectedOptions);
            }

        }

        //if there is no current value and the field allows null, we add the placeholder first.
       /* if($allowsNull && $item === false) {
            var $option = new Option('', '', true, true);
            $(element).prepend($option);
        //if element does not allow null we select the first available option
        }else if(!$allowsNull && $item === false) {

            //if there is any option we select the first, otherwise we place the placeholder.
            if(Object.keys($optionsForSelect).length > 0) {
                $(element).val(Object.keys($optionsForSelect)[0]);
            }else{
                var $option = new Option('', '', true, true);
                $(element).prepend($option);
            }
        }*/
        if (!$allows_null && $item === false) {
            if(Object.keys($optionsForSelect).length > 0) {
                $(element).val(Object.keys($optionsForSelect)[0]);
            }else{
                $(element).val(null);
            }
        }

        $(element).attr('data-current-value',$(element).val());
        $(element).trigger('change');

        var $select2Settings = {
                theme: 'bootstrap',
                multiple: $multiple,
                placeholder: $placeholder,
                allowClear: $allowClear,
            };
        if (!$(element).hasClass("select2-hidden-accessible"))
        {
            $(element).select2($select2Settings);
             // if any dependencies have been declared
            // when one of those dependencies changes value
            // reset the select2 value
            for (var i=0; i < $dependencies.length; i++) {
                $dependency = $dependencies[i];
                $('input[name='+$dependency+'], select[name='+$dependency+'], checkbox[name='+$dependency+'], radio[name='+$dependency+'], textarea[name='+$dependency+']').change(function () {
                    element.val(null).trigger("change");
                });

            }
        }
    }
</script>
@endpush
@endif
{{-- End of Extra CSS and JS --}}
{{-- ########################################## --}}
