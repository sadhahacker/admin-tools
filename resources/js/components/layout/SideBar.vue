<template>
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark"> <!--begin::Sidebar Brand-->
    <div class="sidebar-brand">
        <router-link to="/" class="brand-link">
            <img src="" alt="Logo" class="brand-image opacity-75 shadow">
            <span class="brand-text fw-light">Sadha</span>
        </router-link>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2"> <!--begin::Sidebar Menu-->
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
            <li class="nav-item">
                <router-link to="/" class="nav-link">
                    <i class="nav-icon bi bi-download"></i>
                    <p>Dashboard</p>
                </router-link>
            </li>
            <li class="nav-item">
                <router-link to="/signals" class="nav-link">
                    <i class="nav-icon bi bi-download"></i>
                    <p>Signals</p>
                </router-link>
            </li>
        </ul> <!--end::Sidebar Menu-->
      </nav>
    </div> <!--end::Sidebar Wrapper-->
  </aside>
</template>

<script>
export default {
  name: 'SideBar'
}
</script>
