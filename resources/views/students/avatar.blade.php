@if($s->photo)
    <img src="{{ route('students.photo', $s) }}" class="avatar" alt="">
@else
    <span class="avatar">{{ strtoupper(substr($s->first_name, 0, 1)) }}</span>
@endif
