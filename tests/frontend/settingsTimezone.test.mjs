import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const settings = await readFile(new URL('../../src/views/Settings/ListSettings.vue', import.meta.url), 'utf8')

assert.doesNotMatch(settings, /reportes_recordatorios_zona_horaria/)
assert.doesNotMatch(settings, /t\('employees', 'Time zone'\)/)
assert.match(settings, /The reminder hour uses each user's time zone from their Nextcloud profile\./)

console.log('SETTINGS_TIMEZONE_FRONTEND_OK source=nextcloud-profile')
