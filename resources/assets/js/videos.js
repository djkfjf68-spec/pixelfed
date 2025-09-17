import Vue from 'vue';
import ShortVideos from './components/ShortVideos.vue';

Vue.component('short-videos', ShortVideos);

const app = new Vue({
    el: '#app'
});