<p>{!! nl2br(e($data['description'])) !!}</p>
<p>Auteur CRM : {{ $user->name }} (ID {{ $user->getKey() }})</p>
<p>Type : {{ $data['type'] }}</p>
<p>Date UTC : {{ $submittedAt->toIso8601String() }}</p>
@if (! empty($data['page_path']))
    <p>Page CRM : {{ $data['page_path'] }}</p>
@endif
