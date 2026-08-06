import Vue, { nextTick } from 'vue'
import App from './views/Settings/Settings.vue'

import mitt from 'mitt'
import { startContextualFieldHelp } from './utils/contextualFieldHelp.js'

Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)

const emitter = mitt()
Vue.prototype.$bus = emitter

const view = new View().$mount('#admin')

nextTick(() => startContextualFieldHelp(view.$el))
