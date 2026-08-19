import { Link, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

const nav = [['Dashboard', 'grid', '/dashboard'], ['Organizations', 'building', '/portal/organizations'], ['People', 'users', '/portal/people'], ['Visitors', 'badge', '/portal/visitors'], ['Invitations', 'mail', '/portal/invitations'], ['Entry & exit', 'scan', '/portal/entry-exit'], ['Students', 'book', '/portal/students'], ['Pickup', 'shield', '/portal/pickup'], ['Vehicle tracking', 'truck', '/portal/vts'], ['Panic & emergency', 'alert', '/portal/pbs'], ['Reports', 'chart', '/portal/reports'], ['Audit logs', 'clock', '/portal/audit-logs']];

function Icon({ name, className = 'h-5 w-5' }: { name: string; className?: string }) {
    const paths: Record<string, string> = {
        grid: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z', building: 'M4 21V5l8-3 8 3v16M8 9h2m4 0h2M8 13h2m4 0h2M8 17h2m4 0h2', users: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87', badge: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4', mail: 'M4 4h16v16H4zM4 6l8 7 8-7', book: 'M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5z', shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', chart: 'M4 20V10m6 10V4m6 16v-7m6 7H2', clock: 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6v6l4 2', scan:'M3 7V3h4M17 3h4v4M21 17v4h-4M7 21H3v-4M7 12h10', truck:'M3 6h11v10H3zM14 10h4l3 3v3h-7zM7 20a2 2 0 100-4 2 2 0 000 4zM19 20a2 2 0 100-4 2 2 0 000 4z', alert:'M12 9v4M12 17h.01M10.3 3.7L2 18a2 2 0 001.7 3h16.6a2 2 0 001.7-3L13.7 3.7a2 2 0 00-3.4 0z', bell: 'M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4', search: 'M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35', menu: 'M4 6h16M4 12h16M4 18h16',
    };
    return <svg className={className} viewBox="0 0 24 24" fill={name === 'grid' ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d={paths[name] ?? paths.grid} /></svg>;
}

export default function Authenticated({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage<PageProps>().props;
    const user = page.auth.user;
    const invitationCount = page.navigation?.activeInvitations ?? 0;
    const notifications = page.notifications?.items ?? [];
    const unreadCount = page.notifications?.unreadCount ?? 0;
    const enabledModules = page.enabledModules ?? [];
    const [open, setOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const criticalAlert = notifications.find(item => item.type === 'critical' && !item.read_at);
    const roleAccess: Record<string, string[]> = {
        super_admin: nav.map(item => item[2]),
        organization_admin: ['/dashboard','/portal/people','/portal/visitors','/portal/invitations','/portal/entry-exit','/portal/students','/portal/pickup','/portal/vts','/portal/pbs','/portal/reports','/portal/audit-logs'],
        security_officer: ['/dashboard','/portal/entry-exit','/portal/pickup','/portal/vts','/portal/pbs'],
        staff: ['/dashboard','/portal/visitors','/portal/invitations','/portal/vts','/portal/pbs'],
        resident: ['/dashboard','/portal/visitors','/portal/invitations','/portal/pbs'],
        parent: ['/dashboard','/portal/students','/portal/pickup','/portal/pbs'], guardian: ['/dashboard','/portal/students','/portal/pickup','/portal/pbs'],
    };
    const moduleForPath:Record<string,string>={'/portal/visitors':'VAS','/portal/invitations':'VAS','/portal/entry-exit':'VAS','/portal/students':'PAS','/portal/pickup':'PAS','/portal/vts':'VTS','/portal/pbs':'PBS'};
    const visible = (items: string[][]) => items.filter(item => (roleAccess[user.role ?? ''] ?? ['/dashboard']).includes(item[2]) && (!moduleForPath[item[2]] || enabledModules.includes(moduleForPath[item[2]])));
    useEffect(() => { const timer = window.setInterval(() => router.reload({ only: ['notifications', 'navigation'] }), 15000); return () => window.clearInterval(timer); }, []);
    return <div className="app-shell">
        {criticalAlert && <div className="critical-alert-backdrop"><section className="critical-alert-dialog" role="alertdialog" aria-modal="true"><span>!</span><small>CRITICAL SECURITY ALERT</small><h2>{criticalAlert.title}</h2><p>{criticalAlert.message}</p><div><button className="btn-secondary" onClick={() => { router.patch(`/notifications/${criticalAlert.id}/read`, {}, { preserveScroll: true }); if (criticalAlert.action_url) router.visit(criticalAlert.action_url); }}>Open response console</button><button className="danger-action" onClick={() => router.patch(`/notifications/${criticalAlert.id}/read`, {}, { preserveScroll: true })}>Acknowledge</button></div></section></div>}
        {open && <button aria-label="Close navigation" className="nav-scrim" onClick={() => setOpen(false)} />}
        <aside className={`sidebar ${open ? 'sidebar-open' : ''}`}>
            <Link href="/dashboard" className="brand"><span className="brand-mark">N</span><span>NEMTRACK<small>SECURE EVERY MOVE</small></span></Link>
            <div className="org-chip"><span className="org-avatar">{user.role === 'super_admin' ? 'NT' : (user.organization?.name ?? 'NM').slice(0, 2).toUpperCase()}</span><span><strong>{user.role === 'super_admin' ? 'NEMTRACK Platform' : user.organization?.name ?? 'Organization'}</strong><small>{user.role === 'super_admin' ? 'Super administrator console' : 'Organization portal'}</small></span><span className="chevron">⌄</span></div>
            <nav className="side-nav" aria-label="Main navigation"><p>WORKSPACE</p>{visible(nav.slice(0,3)).map(([label,icon,href])=><Link key={label} href={href} className={location.pathname===href?'active':''}><Icon name={icon}/>{label}</Link>)}{visible(nav.slice(3,8)).length>0&&<p>ACCESS CONTROL</p>}{visible(nav.slice(3,8)).map(([label,icon,href])=><Link key={label} href={href} className={location.pathname===href?'active':''}><Icon name={icon}/>{label}{label==='Invitations'&&invitationCount>0&&<em>{invitationCount>99?'99+':invitationCount}</em>}</Link>)}{visible(nav.slice(8,10)).length>0&&<p>SAFETY & MOBILITY</p>}{visible(nav.slice(8,10)).map(([label,icon,href])=><Link key={label} href={href} className={location.pathname===href?'active':''}><Icon name={icon}/>{label}</Link>)}{visible(nav.slice(10)).length>0&&<p>INSIGHTS</p>}{visible(nav.slice(10)).map(([label,icon,href])=><Link key={label} href={href} className={location.pathname===href?'active':''}><Icon name={icon}/>{label}</Link>)}</nav>
            <div className="sidebar-help"><span>?</span><strong>Need help?</strong><small>Visit the support centre</small></div>
        </aside>
        <section className="workspace"><header className="topbar"><button className="menu-button" onClick={() => setOpen(true)}><Icon name="menu" /></button><div className="global-search"><Icon name="search" /><input aria-label="Search" placeholder="Search people, passes, students..." /><kbd>⌘ K</kbd></div><div className="notification-wrap"><button className="icon-button" aria-label={`Notifications, ${unreadCount} unread`} onClick={() => setNotificationsOpen(v => !v)}><Icon name="bell" />{unreadCount > 0 && <i />}</button>{notificationsOpen && <div className="notification-panel"><header><div><b>Notifications</b><span>{unreadCount} unread</span></div>{unreadCount > 0 && <button onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}>Mark all read</button>}</header><div className="notification-list">{notifications.length ? notifications.map(item => <button key={item.id} className={item.read_at ? 'read' : ''} onClick={() => { if (!item.read_at) router.patch(`/notifications/${item.id}/read`, {}, { preserveScroll: true }); setNotificationsOpen(false); if (item.action_url) router.visit(item.action_url); }}><span className={`notification-symbol ${item.type}`}>{item.type === 'critical' ? '!' : '✓'}</span><div><b>{item.title}</b><p>{item.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></div>{!item.read_at && <i />}</button>) : <div className="notification-empty"><span>♧</span><b>You’re all caught up</b><p>New security activity will appear here.</p></div>}</div></div>}</div><Link href={route('profile.edit')} className="user-chip"><span>{user.name?.split(' ').map((n: string) => n[0]).join('').slice(0, 2)}</span><span><strong>{user.name}</strong><small>{user.role?.replaceAll('_',' ') ?? 'Administrator'}</small></span></Link><Link href={route('logout')} method="post" as="button" className="header-logout" title="Sign out"><span>↪</span><b>Log out</b></Link></header>{header}<main className="main-content">{children}</main></section>
    </div>;
}
