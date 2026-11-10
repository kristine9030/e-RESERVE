<div class="dropdown bell {{ $bellClass ?? '' }}">
    <button class="icon-btn bell-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications{{ $unread ? ' (' . $unread . ' unread)' : '' }}">
        <i class="fa-solid fa-bell"></i>
        @if ($unread)<span class="bell-count">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </button>
    <div class="dropdown-menu dropdown-menu-end app-drop shadow border-0 mt-2 bell-menu">
        <div class="bell-head">
            <strong>Notifications</strong>
            @if ($unread)
                <form method="post" action="{{ route('notifications.read') }}">@csrf
                    <button type="submit" class="bell-readall">Mark all as read</button>
                </form>
            @endif
        </div>
        @forelse ($notices as $n)
            <a href="{{ route('notifications.index', ['open' => $n->id]) }}" class="bell-item {{ $n->read_at ? '' : 'unread' }}">
                <span class="bell-ico t-{{ $n->type }}"><i class="fa-solid {{ $noticeIcon[$n->type] ?? 'fa-bell' }}"></i></span>
                <span class="bell-txt">
                    <span class="bell-title">{{ $n->title }}</span>
                    @if ($n->body)<span class="bell-body">{{ $n->body }}</span>@endif
                    <span class="bell-time">{{ $n->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <div class="bell-empty"><i class="fa-regular fa-bell"></i>Nothing yet. Updates from SSC show up here.</div>
        @endforelse
        <a href="{{ route('notifications.index') }}" class="bell-all">View all notifications <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</div>
