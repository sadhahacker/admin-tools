<template>
    <div class="app-wrapper">
        <NavBar/>
        <SideBar/>
        <div class="container">
            <router-view /> <!-- This is where your page content will load -->
        </div>
        <Footer/>
    </div>
</template>

<script>
import NavBar from "./components/layout/NavBar.vue";
import Footer from "./components/layout/Footer.vue";
import SideBar from "./components/layout/SideBar.vue";
export default {
    components: {
        NavBar,
        Footer,
        SideBar,
    },
};
</script>

<style>

</style>
