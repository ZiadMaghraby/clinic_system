@props(['name','label','type'=>'text','value'=>'','required'=>false])
<label class="field"><span>{{ __($label) }} @if($required)<span aria-hidden="true">*</span>@endif</span><input type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : old($name,$value) }}" @required($required) {{ $attributes }}></label>
