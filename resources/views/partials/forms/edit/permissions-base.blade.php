@foreach ($permissions as $area => $permissionsArray)
  @if (count($permissionsArray) == 1)
    <?php $localPermission = $permissionsArray[0]; ?>

    @php
      // As of now, $localPermissionName will be "superuser", "admin", "import", or "reports.view"
      $localPermissionName = $localPermission['permission'];
      $inputName = 'permission['.$localPermissionName.']';
    @endphp

    <tbody class="permissions-group">
    <tr class="header-row permissions-row">
      <td class="col-md-5 tooltip-base permissions-item"
        data-tooltip="true"
        data-placement="right"
        title="{{ $localPermission['note'] }}"
      >
        @unless (empty($localPermission['label']))
         <h2>{{ $area . ': ' . $localPermission['label'] }}</h2>
        @else
          <h2>{{ $area }}</h2>
        @endunless
      </td>

      <td class="col-md-1 permissions-item">
        <label class="sr-only" for="{{ $inputName }}">{{ $inputName }}</label>
        @if (($localPermissionName == 'superuser') && (!Auth::user()->isSuperUser()))
          {{ Form::radio($inputName, '1',$userPermissions[$localPermissionName] == '1',['disabled'=>"disabled", 'aria-label'=> $inputName]) }}
        @elseif (($localPermissionName == 'admin') && (!Auth::user()->hasAccess('admin')))
          {{ Form::radio($inputName, '1',$userPermissions[$localPermissionName] == '1',['disabled'=>"disabled", 'aria-label'=> $inputName]) }}
        @else
          {{ Form::radio($inputName, '1',$userPermissions[$localPermissionName] == '1',['value'=>"grant",  'aria-label'=> $inputName]) }}
        @endif

        
      </td>
      <td class="col-md-1 permissions-item">
        <label class="sr-only" for="{{ $inputName }}">{{ $inputName }}</label>
        @if (($localPermissionName == 'superuser') && (!Auth::user()->isSuperUser()))
          {{ Form::radio($inputName, '-1',$userPermissions[$localPermissionName] == '-1',['disabled'=>"disabled", 'aria-label'=> $inputName]) }}
        @elseif (($localPermissionName == 'admin') && (!Auth::user()->hasAccess('admin')))
          {{ Form::radio($inputName, '-1',$userPermissions[$localPermissionName] == '-1',['disabled'=>"disabled", 'aria-label'=> $inputName]) }}
        @else
          {{ Form::radio($inputName, '-1',$userPermissions[$localPermissionName] == '-1',['value'=>"deny",   'aria-label'=> $inputName]) }}
        @endif
      </td>
      <td class="col-md-1 permissions-item">
        <label class="sr-only" for="{{ $inputName }}">
           {{ $inputName }}</label>
        @if (($localPermissionName == 'superuser') && (!Auth::user()->isSuperUser()))
          {{ Form::radio($inputName,'0',$userPermissions[$localPermissionName] == '0',['disabled'=>"disabled", 'aria-label'=> $inputName] ) }}
        @elseif (($localPermissionName == 'admin') && (!Auth::user()->hasAccess('admin')))
          {{ Form::radio($inputName,'0',$userPermissions[$localPermissionName] == '0',['disabled'=>"disabled", 'aria-label'=> $inputName] ) }}
        @else
          {{ Form::radio($inputName,'0',$userPermissions[$localPermissionName] == '0',['value'=>"inherit",   'aria-label'=> $inputName] ) }}
        @endif
      </td>
    </tr>
  </tbody>
    @php
      unset($localPermissionName);
      unset($inputName);
    @endphp

  @else <!-- count($permissionsArray) == 1-->
  <tbody class="permissions-group">
    <tr class="header-row permissions-row">
      <td class="col-md-5 header-name">
        <h2> {{ $area }}</h2>
      </td>
      <td class="col-md-1 permissions-item">
        <label for="{{ $area }}" class="sr-only">{{ $area }}</label>
        {{ Form::radio("$area", '1',false,['value'=>"grant", 'data-checker-group' => str_slug($area), 'aria-label' => $area]) }}
      </td>
      <td class="col-md-1 permissions-item">
        <label for="{{ $area }}" class="sr-only">{{ $area }}</label>
        {{ Form::radio("$area", '-1',false,['value'=>"deny", 'data-checker-group' => str_slug($area), 'aria-label' => $area]) }}
      </td>
      <td class="col-md-1 permissions-item">
        <label for="{{ $area }}" class="sr-only">{{ $area }}</label>
        {{ Form::radio("$area", '0',false,['value'=>"inherit", 'data-checker-group' => str_slug($area), 'aria-label' => $area] ) }}
      </td>
    </tr>

    @foreach ($permissionsArray as $index => $permission)
      @php
        // "assets.view", "consumables.create", etc...
        $permissionName = $permission['permission'];
        $inputName = 'permission['.$permissionName.']';
      @endphp
      <tr class="permissions-row">
        @if ($permission['display'])
          <td
            class="col-md-5 tooltip-base permissions-item"
            data-tooltip="true"
            data-placement="right"
            title="{{ $permission['note'] }}"
          >
            {{ $permission['label'] }}
          </td>
          <td class="col-md-1 permissions-item">
            <label class="sr-only" for="{{ $inputName }}">{{ $inputName }}</label>

            @if (($permissionName == 'superuser') && (!Auth::user()->isSuperUser()))
              {{ Form::radio($inputName, '1', $userPermissions[$permissionName] == '1', ["value"=>"grant", 'disabled'=>'disabled', 'class'=>'radiochecker-'.str_slug($area), 'aria-label'=>$inputName]) }}
            @else
              {{ Form::radio($inputName, '1', $userPermissions[$permissionName] == '1', ["value"=>"grant",'class'=>'radiochecker-'.str_slug($area), 'aria-label' =>$inputName]) }}
            @endif
          </td>
          <td class="col-md-1 permissions-item">
            @if (($permissionName == 'superuser') && (!Auth::user()->isSuperUser()))
              {{ Form::radio($inputName, '-1', $userPermissions[$permissionName] == '-1', ["value"=>"deny", 'disabled'=>'disabled', 'class'=>'radiochecker-'.str_slug($area), 'aria-label'=>$inputName]) }}
            @else
              {{ Form::radio($inputName, '-1', $userPermissions[$permissionName] == '-1', ["value"=>"deny",'class'=>'radiochecker-'.str_slug($area), 'aria-label'=>$inputName]) }}
            @endif
          </td>
          <td class="col-md-1 permissions-item">
            @if (($permissionName == 'superuser') && (!Auth::user()->isSuperUser()))
              {{ Form::radio($inputName, '0', $userPermissions[$permissionName] =='0', ["value"=>"inherit", 'disabled'=>'disabled', 'class'=>'radiochecker-'.str_slug($area), 'aria-label'=>$inputName]) }}
            @else
              {{ Form::radio($inputName, '0', $userPermissions[$permissionName] =='0', ["value"=>"inherit", 'class'=>'radiochecker-'.str_slug($area), 'aria-label'=>$inputName]) }}
            @endif
          </td>
        @endif
      </tr>
      @php
        unset($permissionName);
        unset($inputName);
      @endphp
    @endforeach
    </tbody>
  @endif
@endforeach
