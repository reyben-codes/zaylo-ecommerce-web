<aside class="sidebar">
    <ul class="sidebar-menu">
        <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="{{ route('admin.registrations') }}" class="{{ request()->routeIs('admin.registrations') ? 'active' : '' }}"><i class="fas fa-user-plus"></i><span>Registrations</span><span class="badge">5</span></a></li>
        <li><a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}"><i class="fas fa-users"></i><span>Users</span></a></li>
        <li><a href="{{ route('admin.compliance') }}" class="{{ request()->routeIs('admin.compliance*') ? 'active' : '' }}"><i class="fas fa-shield-alt"></i><span>Compliance</span></a></li>
        <li><a href="{{ route('admin.disputes') }}" class="{{ request()->routeIs('admin.disputes') ? 'active' : '' }}"><i class="fas fa-gavel"></i><span>Disputes</span><span class="badge">2</span></a></li>
        <li><a href="{{ route('admin.commissions') }}" class="{{ request()->routeIs('admin.commissions') ? 'active' : '' }}"><i class="fas fa-percent"></i><span>Commissions</span></a></li>
        <li><a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
        <li><a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}"><i class="fas fa-cog"></i><span>Settings</span></a></li>
        <li><a href="{{ route('admin.chat') }}" class="{{ request()->routeIs('admin.chat') ? 'active' : '' }}"><i class="fas fa-comments"></i><span>Chat</span><span class="badge">3</span></a></li>
        <li><a href="{{ route('admin.account') }}" class="{{ request()->routeIs('admin.account') ? 'active' : '' }}"><i class="fas fa-user"></i><span>Account</span></a></li>
        <li class="sidebar-divider"></li>
        <li><a href="{{ route('login') }}"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</aside>
