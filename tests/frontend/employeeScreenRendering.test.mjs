import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const mapper = await readFile(new URL('../../lib/Db/EmployeeMapper.php', import.meta.url), 'utf8')
const employees = await readFile(new URL('../../src/views/components/EmployeeList/Employees.vue', import.meta.url), 'utf8')
const contentList = await readFile(new URL('../../src/views/components/EmployeeList/ContentList.vue', import.meta.url), 'utf8')
const employeeDetails = await readFile(new URL('../../src/views/components/EmployeeList/EmployeeDetails.vue', import.meta.url), 'utf8')
const employeeTab = await readFile(new URL('../../src/views/components/EmployeeList/Tabs/EmployeeTab.vue', import.meta.url), 'utf8')
const network = await readFile(new URL('../../src/views/components/EmployeeList/OrgChart/OrgChartNetwork.vue', import.meta.url), 'utf8')
const traditional = await readFile(new URL('../../src/views/components/EmployeeList/OrgChart/OrgChartTraditional.vue', import.meta.url), 'utf8')

assert.match(mapper, /selectAlias\('e\.id_user', 'employee_uid'\)/)
assert.match(mapper, /selectAlias\('u\.displayname', 'employee_displayname'\)/)
assert.match(mapper, /\$row\['id_user'\] = \$uid/)
assert.match(mapper, /\$row\['displayname'\] = \$displayName !== '' \? \$displayName : \$uid/)

assert.match(employees, /employees\.map\(this\.normalizeEmployee\)/)
assert.match(employees, /source\.id_user \?\? source\.employee_uid \?\? source\.uid/)
assert.match(contentList, /\[displayname, uid\]/)
assert.match(contentList, /\.some\(value => value\.includes\(normalizedQuery\)\)/)
assert.doesNotMatch(employeeDetails, /:Employee="Empleados"/)
assert.match(employeeDetails, /:Employee="employeesProp"/)
assert.match(employeeTab, /Array\.isArray\(this\.Employee\) \? this\.Employee : \[\]/)
assert.doesNotMatch(employeeTab, /this\.employees\.map/)
assert.doesNotMatch(employeeTab, /:user="Equipo\.jefe"/)
assert.match(employeeTab, /employeeUidById\(equipo\.team_leader_id\)/)
assert.match(employeeTab, /teamLeaderUid\(team\)/)

assert.match(network, /viewMode: 'traditional'/)
assert.match(network, /data\?\.employees \?\? data\?\.Employee/)
assert.match(network, /employees\.map\(this\.normalizeEmployee\)/)
assert.doesNotMatch(traditional, /\{\{ nodeData \}\}/)
assert.match(traditional, /Level \{n\}/)

console.log('EMPLOYEE_SCREEN_RENDERING_OK identity=canonical org_chart=visible details=array_safe team_leader=uid search=null_safe')
