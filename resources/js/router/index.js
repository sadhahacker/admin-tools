import { createRouter, createWebHistory } from 'vue-router';

// Import your components
import Dashboard from "./../components/pages/Dashboard.vue";
import Signals from "@/components/pages/Signals.vue";

// Define routes
const routes = [
    {
        path: '/',
        component: Dashboard,
    },
    {
        path: '/signals',
        component: Signals
    }
];

// Create and export the router
const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
});

export default router;
