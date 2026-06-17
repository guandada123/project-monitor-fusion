<?php

namespace App\Livewire;

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

class NotificationsPanel extends Component
{
    /** @var int 未读通知数 */
    public int $unreadCount = 0;

    /** @var bool 下拉面板是否打开 */
    public bool $show = false;

    /** @var string|null 筛选 */
    public ?string $filter = null; // null = all, 'unread' = unread only

    protected $listeners = ['notificationReceived' => 'refreshUnreadCount'];

    public function mount(): void
    {
        $this->refreshUnreadCount();
    }

    public function refreshUnreadCount(): void
    {
        if (!auth()->check()) {
            $this->unreadCount = 0;
            return;
        }
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }

    /**
     * 标记单条通知为已读
     */
    public function markAsRead(string $notificationId): void
    {
        if (!auth()->check()) return;

        $notification = auth()->user()->notifications()
            ->where('id', $notificationId)
            ->first();

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $this->refreshUnreadCount();
    }

    /**
     * 标记全部为已读
     */
    public function markAllAsRead(): void
    {
        if (!auth()->check()) return;

        auth()->user()->unreadNotifications->markAsRead();
        $this->refreshUnreadCount();
        $this->dispatch('notifications-cleared');
    }

    /**
     * 切换通知筛选
     */
    public function toggleFilter(): void
    {
        $this->filter = $this->filter === 'unread' ? null : 'unread';
    }

    /**
     * 点击通知后的操作：标记已读 + 跳转
     */
    public function handleNotificationClick(string $notificationId): void
    {
        $this->markAsRead($notificationId);
        $this->show = false;
    }

    /**
     * 关闭面板
     */
    public function closePanel(): void
    {
        $this->show = false;
    }

    public function render()
    {
        $notifications = collect();
        if (auth()->check()) {
            $query = auth()->user()->notifications();

            if ($this->filter === 'unread') {
                $query->whereNull('read_at');
            }

            $notifications = $query->latest()->limit(20)->get()->map(function ($n) {
                $data = $n->data;
                return [
                    'id' => $n->id,
                    'type' => $data['type'] ?? 'unknown',
                    'icon' => $data['icon'] ?? 'bell',
                    'message' => $data['message'] ?? '',
                    'url' => $data['url'] ?? '#',
                    'time' => $n->created_at->diffForHumans(),
                    'read' => !is_null($n->read_at),
                    'severity_color' => $data['severity_color'] ?? null,
                ];
            });
        }

        return view('livewire.notifications-panel', [
            'notifications' => $notifications,
        ]);
    }
}
