<ul class="account-nav">
    <li><a href="{{ route('user.index') }}" class="menu-link menu-link_us-s {{ Route::is('user.index') ? 'menu-link_active' : '' }}">Dashboard</a></li>
    <li><a href="{{ route('user.orders') }}" class="menu-link menu-link_us-s {{ Route::is('user.orders') || Route::is('user.order.details') ? 'menu-link_active' : '' }}">Orders</a></li>
    <li><a href="account-address.html" class="menu-link menu-link_us-s">Addresses</a></li>
    <li><a href="account-details.html" class="menu-link menu-link_us-s">Account Details</a></li>
    <li><a href="account-wishlist.html" class="menu-link menu-link_us-s">Wishlist</a></li>

    <li>
        <form method="post" action="{{ route('logout') }}" id="logout-form">
            @csrf
            <a href="{{ route('logout') }}" class="menu-link menu-link_us-s" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
        </form>
    </li>
</ul>
