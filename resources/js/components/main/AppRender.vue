<template>
    <div class="app-wrapper">
        <NavBar/>
        <SideBar/>
        <Container/>
        <Footer/>
    </div>
</template>

<script>

import Container from "@/components/main/Container.vue";
import NavBar from "@/components/main/NavBar.vue";
import SideBar from "@/components/main/SideBar.vue";
import Footer from "@/components/main/Footer.vue";

export default {
    name: 'AppRender',
    components: {Footer, SideBar, NavBar, Container}
}

</script>
