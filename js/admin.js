let departments = [];
let employees = [];
let resignedEmployees = [];
let resigningEmployees = [];
let vacationTypes = [];
let allRequests = [];
let positions = [];
let annualLeaveData = [];

        document.addEventListener('DOMContentLoaded', async () => {
            const res = await api.auth.check();
            if (res.csrf_token) api.setCsrfToken(res.csrf_token);
            loadDepartments();
            loadPositions();
            loadEmployeesWithLeave();
            loadVacationTypes();
            loadAllRequests();
            initAdminEventListeners();
        });

function initAdminEventListeners() {
    const $ = id => document.getElementById(id);
    $('adminLogout')?.addEventListener('click', logout);
    // Tab buttons
    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => showTab(btn.dataset.tab));
    });
    $('tabSettingsDropdown')?.addEventListener('click', toggleSettingsDropdown);
    // Action buttons
    $('btnAddEmployee')?.addEventListener('click', () => showEmployeeModal());
    $('btnAddType')?.addEventListener('click', () => showTypeModal());
    $('btnAddPosition')?.addEventListener('click', () => showPositionModal());
    $('btnAddDepartment')?.addEventListener('click', () => showDepartmentModal());
    $('btnAddHoliday')?.addEventListener('click', () => showHolidayModal());
    // Department modal
    $('btnCloseDept')?.addEventListener('click', closeDepartmentModal);
    $('btnCancelDept')?.addEventListener('click', closeDepartmentModal);
    // Position modal
    $('btnClosePosition')?.addEventListener('click', closePositionModal);
    $('btnCancelPosition')?.addEventListener('click', closePositionModal);
    // Holiday modal
    $('holidayYear')?.addEventListener('change', loadHolidays);
    $('btnCloseHoliday')?.addEventListener('click', closeHolidayModal);
    $('btnCancelHoliday')?.addEventListener('click', closeHolidayModal);
    // Annual leave edit modal
    $('btnCloseAnnualLeaveEdit')?.addEventListener('click', closeAnnualLeaveEditModal);
    $('btnCancelAnnualLeaveEdit')?.addEventListener('click', closeAnnualLeaveEditModal);
    $('btnSaveAnnualLeaveEdit')?.addEventListener('click', saveAnnualLeaveEdit);
    $('annualYearSelect')?.addEventListener('change', loadAnnualLeaveTable);
    // Support complete modal
    $('btnCloseSupportComplete')?.addEventListener('click', closeSupportCompleteModal);
    $('btnCancelSupportComplete')?.addEventListener('click', closeSupportCompleteModal);
    $('btnSaveSupportComplete')?.addEventListener('click', saveSupportComplete);
    // Cert complete modal
    $('btnCloseCertComplete')?.addEventListener('click', closeCertCompleteModal);
    $('btnCancelCertComplete')?.addEventListener('click', closeCertCompleteModal);
    $('btnSaveCertComplete')?.addEventListener('click', saveCertComplete);
    // SMTP settings
    $('btnSaveSmtp')?.addEventListener('click', saveSmtpSettings);
    $('btnTestSmtp')?.addEventListener('click', testSmtpSettings);
    // Password verify modal
    $('btnClosePwVerify')?.addEventListener('click', closePasswordVerifyModal);
    $('pwVerifyToggle')?.addEventListener('click', togglePwVerifyVisible);
    $('btnCancelPwVerify')?.addEventListener('click', closePasswordVerifyModal);
    $('btnConfirmPwVerify')?.addEventListener('click', confirmPasswordVerify);
    // Employee modal
    $('btnCloseEmployee')?.addEventListener('click', closeEmployeeModal);
    $('empIsActive')?.addEventListener('change', function() {
        document.getElementById('empResignDate').style.display = this.checked ? 'block' : 'none';
    });
    $('btnCancelEmployee')?.addEventListener('click', closeEmployeeModal);
    $('btnSaveEmployee')?.addEventListener('click', saveEmployee);
    // Type modal
    $('btnCloseType')?.addEventListener('click', closeTypeModal);
    $('btnCancelType')?.addEventListener('click', closeTypeModal);
}

        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('hidden');
            document.querySelectorAll('[data-tab]').forEach(btn => {
                if (btn.dataset.tab === tab) {
                    btn.classList.add('active');
                }
            });
            // Highlight settings dropdown trigger when a sub-tab is active
            const settingsTabs = ['vacationTypes', 'positions', 'departments', 'holidays', 'settings'];
            if (settingsTabs.includes(tab)) {
                const parentBtn = document.querySelector('.tab-dropdown > .tab');
                if (parentBtn) parentBtn.classList.add('active');
            }
            
            if (tab === 'holidays') {
                initHolidayYearSelect();
                loadHolidays();
            }
if (tab === 'resigned') {
    loadResignedEmployees();
}
if (tab === 'resigning') {
    loadResigningEmployees();
}
            if (tab === 'annualLeave') {
                loadAnnualLeaveTable();
            }
            if (tab === 'departments') {
                loadDepartments();
            }
            if (tab === 'certificate') {
                loadCertificates();
            }
            if (tab === 'support') {
                loadSupportRequests();
            }
            if (tab === 'settings') {
                loadSmtpSettings();
            }

            // Close settings dropdown if open
            document.querySelectorAll('.tab-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        }

        function toggleSettingsDropdown(e) {
            e.stopPropagation();
            const menu = document.querySelector('.tab-dropdown-menu');
            if (!menu) return;
            const isOpen = menu.classList.contains('show');
            document.querySelectorAll('.tab-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            if (!isOpen) {
                const btn = e.currentTarget;
                const rect = btn.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.left = rect.left + 'px';
                menu.classList.add('show');
            }
        }

        // Close dropdown on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.tab-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        });

        async function loadHolidays() {
            const year = document.getElementById('holidayYear').value;
            try {
                const res = await fetch('api/vacation_requests.php?action=holidays&year=' + year);
                const data = await res.json();
                renderHolidays(data.data || []);
            } catch (err) {
                console.error('Load holidays error:', err);
            }
        }

        function renderHolidays(holidays) {
            const tbody = document.getElementById('holidaysList');
            if (!holidays || holidays.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">공휴일 데이터가 없습니다.</td></tr>';
                return;
            }
            tbody.innerHTML = holidays.map(h => `
                <tr>
                    <td>${h.date}</td>
                    <td>${h.name}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="showHolidayModal(${h.id}, '${escapeHtml(h.date)}', '${escapeHtml(h.name)}')">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteHoliday(${h.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }

        function showHolidayModal(id = null, date = '', name = '') {
            document.getElementById('holidayId').value = id || '';
            document.getElementById('holidayDate').value = date;
            document.getElementById('holidayName').value = name;
            document.getElementById('holidayModalTitle').textContent = id ? '공휴일 수정' : '공휴일 추가';
            document.getElementById('holidayModal').classList.remove('hidden');
        }

        function closeHolidayModal() {
            document.getElementById('holidayModal').classList.add('hidden');
        }

        async function saveHoliday(e) {
            e.preventDefault();
            const id = document.getElementById('holidayId').value;
            const date = document.getElementById('holidayDate').value;
            const name = document.getElementById('holidayName').value;
            const year = date.split('-')[0];
            
            try {
                const res = await api.holidays.save({ id, date, name, year });
                if (res.success) {
                    alert('저장되었습니다.');
                    closeHolidayModal();
                    loadHolidays();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function deleteHoliday(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            
            try {
                const res = await api.holidays.delete(id);
                if (res.success) {
                    alert('삭제되었습니다.');
                    loadHolidays();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function loadDepartments() {
            const res = await api.employees.getDepartments();
            departments = res.data;
            populateDepartmentSelects();
            renderDepartments();
        }

        function populateDepartmentSelects() {
            const options = '<option value="">선택하세요</option>' + 
                departments.map(d => `<option value="${d.id}">${d.name}${d.code ? ' (' + d.code + ')' : ''}</option>`).join('');
            document.getElementById('empDept').innerHTML = options;
            document.getElementById('managedDept').innerHTML = options;
        }

        let employeesLeaveData = {};
let resigningSeveranceUsageCache = {};
        
        async function loadEmployeesWithLeave() {
            const res = await api.employees.list({ active: 1 });
            employees = res.data;
            
            const currentYear = new Date().getFullYear();
            
            try {
                const res2 = await api.vacationRequests.employeeLeaveList(currentYear);
                employeesLeaveData = res2.data || {};
            } catch (err) {
                employeesLeaveData = {};
            }
            
            renderEmployees();
        }

async function loadResignedEmployees() {
    document.getElementById('resignedVacationHistory').style.display = 'none';
    const res = await api.employees.list({ active: 0 });
    resignedEmployees = res.data;
    renderResignedEmployees();
}

 async function loadResigningSeveranceUsage() {
     if (resigningEmployees.length === 0) return;
     
     try {
         const currentYear = new Date().getFullYear();
         // Load severance usage for each resigning employee
         for (const employee of resigningEmployees) {
             const res = await api.employees.severance_usage(employee.id, currentYear);
             if (res.success) {
                resigningSeveranceUsageCache[employee.id] = {
                    granted: res.data.severance_granted || 0,
                    used: res.data.severance_used || 0,
                    remaining: res.data.severance_remaining || 0
                };
             }
         }
     } catch (err) {
         console.error('Failed to load severance usage:', err);
         // Continue with empty cache - UI will show 0 values
     }
 }

async function loadResigningEmployees() {
     document.getElementById('resigningVacationHistory').style.display = 'none';
     // First get all employees
     const res = await api.employees.list();
     // Filter for resigning employees (is_resigning = 1)
     resigningEmployees = res.data.filter(e => e.is_resigning == 1);
     // Load severance usage for resigning employees
     await loadResigningSeveranceUsage();
     renderResigningEmployees();
 }

        function renderResignedEmployees() {
            document.getElementById('resignedList').innerHTML = resignedEmployees.map(e => `
                <tr onclick="showResignedHistory(${e.id}, '${escapeHtml(e.name)}')" style="cursor:pointer;">
                    <td>${e.emp_no}</td>
                    <td>${e.name}</td>
                    <td>${e.department_name || '-'}</td>
                    <td>${e.position_name || '-'}</td>
                    <td>${e.hire_date || '-'}</td>
                    <td>${e.resignation_date || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editEmployee(${e.id})">수정</button>
                    </td>
                </tr>
            `).join('');
         }

         function renderResigningEmployees() {
             document.getElementById('resigningList').innerHTML = resigningEmployees.map(e => {
                 // Get severance usage for this employee
                 const usage = resigningSeveranceUsageCache[e.id] || { used: 0, remaining: 0 };
                 return `
                 <tr onclick="showResigningHistory(${e.id}, '${escapeHtml(e.name)}')" style="cursor:pointer;">
                     <td>${e.emp_no}</td>
                     <td>${e.name}</td>
                     <td>${e.department_name || '-'}</td>
                     <td>${e.position_name || '-'}</td>
                     <td>${e.hire_date || '-'}</td>
                     <td>${usage.remaining.toFixed(1)}</td>
                     <td>${(parseFloat(e.annual_leave) || 0).toFixed(1)}</td>
                     <td>${(usage.remaining + (parseFloat(e.annual_leave) || 0)).toFixed(1)}</td>
                     <td>
                         <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editEmployee(${e.id})">수정</button>
                     </td>
                 </tr>
                 `;
             }).join('');
         }

         async function showResignedHistory(empId, empName) {
            document.getElementById('resignedEmployeeName').textContent = `${empName} - 휴가 신청 내역`;
            document.getElementById('resignedVacationHistory').style.display = 'block';
            
            try {
                const res = await api.vacationRequests.list(null, null, empId, null, false);
                const list = res.data || [];
                
                document.getElementById('resignedVacationList').innerHTML = list.map(r => `
                    <tr>
                        <td>${r.vacation_type_name || '-'}</td>
                        <td>${r.start_date} ~ ${r.end_date}</td>
                        <td>${r.days}</td>
                        <td><span class="status-badge status-${r.status}">${r.status === 'applied' ? '신청' : r.status === 'approved' ? '승인' : r.status === 'cancelled' ? '취소' : r.status}</span></td>
                        <td style="text-align:left;">${r.reason || '-'}</td>
                    </tr>
                `).join('');
            } catch (err) {
                document.getElementById('resignedVacationList').innerHTML = '<tr><td colspan="5">내역을 불러올 수 없습니다.</td></tr>';
            }
        }

        async function showResigningHistory(empId, empName) {
            document.getElementById('resigningEmployeeName').textContent = `${empName} - 휴가 신청 내역`;
            document.getElementById('resigningVacationHistory').style.display = 'block';
            
            try {
                const res = await api.vacationRequests.list(null, null, empId, null, false);
                const list = res.data || [];
                
                document.getElementById('resigningVacationList').innerHTML = list.map(r => `
                    <tr>
                        <td>${r.vacation_type_name || '-'}</td>
                        <td>${r.start_date} ~ ${r.end_date}</td>
                        <td>${r.days}</td>
                        <td><span class="status-badge status-${r.status}">${r.status === 'applied' ? '신청' : r.status === 'approved' ? '승인' : r.status === 'cancelled' ? '취소' : r.status}</span></td>
                        <td style="text-align:left;">${r.reason || '-'}</td>
                    </tr>
                `).join('');
            } catch (err) {
                document.getElementById('resigningVacationList').innerHTML = '<tr><td colspan="5">내역을 불러올 수 없습니다.</td></tr>';
            }
        }

        function renderEmployees() {
            // roleNames is defined globally in the head section
            const currentYear = new Date().getFullYear();
            
            document.getElementById('employeesList').innerHTML = employees.map(e => `
                <tr>
                    <td>${e.emp_no}</td>
                    <td>${e.hire_date || '-'}</td>
                    <td>${e.name}</td>
                    <td>${e.department_name || '-'}</td>
                    <td>${e.position_name || '-'}</td>
                    <td><span class="role-badge role-${e.role}">${roleNames[e.role]}</span></td>
                    <td>${(employeesLeaveData[e.id]?.granted || parseFloat(e.annual_leave) || 0).toFixed(1)}</td>
                    <td>${(employeesLeaveData[e.id]?.used || 0).toFixed(1)}</td>
                    <td>${(employeesLeaveData[e.id]?.remaining || parseFloat(e.annual_leave) || 0).toFixed(1)}</td>
                    <td><span class="status-badge status-${e.is_active == 1 ? 'active' : 'inactive'}">${e.is_active == 1 ? '재직' : '퇴사'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editEmployee(${e.id})">수정</button>
                        ${e.id != currentUserId ? `<button class="btn btn-sm btn-danger" onclick="deleteEmployee(${e.id})">삭제</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

function showEmployeeModal(id = null) {
    document.getElementById('employeeModal').classList.remove('hidden');
    document.getElementById('employeeForm').reset();
    document.getElementById('empId').value = '';
    document.getElementById('employeeModalTitle').textContent = '사원 추가';
    document.getElementById('empHireDate').value = '';
    document.getElementById('empEmail').value = '';
    document.getElementById('empBirthDate').value = '';
    document.getElementById('empAddress').value = '';
    document.getElementById('empResidentNo').value = '';
    document.getElementById('empIsActive').checked = false;
    document.getElementById('empResignationDate').value = '';
    document.getElementById('empResignDate').style.display = 'none';
    document.getElementById('empIsResigning').checked = false;
    document.getElementById('empVisibleToExec').checked = false;
    document.getElementById('empSeveranceLeave').value = '';
    document.getElementById('empResignGroup').style.display = 'none';
    document.getElementById('empSeveranceGroup').style.display = 'none';
    
    if (!id) {
        document.getElementById('empNo').disabled = false;
    }
}

        function closeEmployeeModal() {
            document.getElementById('employeeModal').classList.add('hidden');
        }

async         function editEmployee(id) {
    const res = await api.employees.get(id);
    if (!res.success) return;
    const emp = res.data;

    document.getElementById('employeeModal').classList.remove('hidden');
    document.getElementById('employeeModalTitle').textContent = '사원 수정';
    document.getElementById('empId').value = emp.id;
    document.getElementById('empNo').value = emp.emp_no;
    document.getElementById('empNo').disabled = true;
    document.getElementById('empName').value = emp.name;
    document.getElementById('empDept').value = emp.department_id || '';
    document.getElementById('empPosition').value = emp.position_id || '';
    document.getElementById('empRole').value = emp.role;
    document.getElementById('managedDept').value = emp.managed_department_id || '';
    document.getElementById('empPhone1').value = emp.phone1 || '';
    document.getElementById('empPhone2').value = emp.phone2 || '';
    document.getElementById('empEmail').value = emp.email || '';
    document.getElementById('empBirthDate').value = emp.birth_date || '';
    document.getElementById('empAddress').value = emp.address || '';
    const residentNoEl = document.getElementById('empResidentNo');
    residentNoEl.value = '********';
    residentNoEl.dataset.original = emp.resident_no || '';
    residentNoEl.type = 'password';
    residentNoEl.readOnly = true;
    document.getElementById('residentNoToggle').textContent = '🔒';
    document.getElementById('empHireDate').value = emp.hire_date || '';
    document.getElementById('empIsActive').checked = emp.is_active == 0;
    document.getElementById('empIsResigning').checked = emp.is_resigning == 1;
    document.getElementById('empResignationDate').value = emp.resignation_date || '';
    document.getElementById('empVisibleToExec').checked = emp.visible_to_exec == 1;
    
    // Show resigning-related fields if employee is resigning or if we're checking the resigning checkbox
    const isResigning = emp.is_resigning == 1;
    document.getElementById('empResignGroup').style.display = 'block';
    document.getElementById('empSeveranceGroup').style.display = 'block';
    if (isResigning) {
        document.getElementById('empResignDate').style.display = 'block';
        document.getElementById('empSeveranceLeave').value = emp.severance_leave || '';
    } else {
        document.getElementById('empResignDate').style.display = 'none';
        document.getElementById('empSeveranceLeave').value = '';
    }
}

        async function saveEmployee(e) {
            if (e) { e.preventDefault(); }
            
            const getVal = (id) => document.getElementById(id)?.value || '';
            
            const id = getVal('empId');
const data = {
    emp_no: getVal('empNo'),
    name: getVal('empName'),
    department_id: getVal('empDept'),
    position_id: getVal('empPosition'),
    role: getVal('empRole'),
    managed_department_id: getVal('managedDept'),
    phone1: getVal('empPhone1'),
    phone2: getVal('empPhone2'),
    email: getVal('empEmail'),
    birth_date: getVal('empBirthDate'),
    address: getVal('empAddress'),
    resident_no: document.getElementById('empResidentNo').type === 'password' ? '' : getVal('empResidentNo'),
    hire_date: getVal('empHireDate'),
    is_active: document.getElementById('empIsActive').checked ? '0' : '1',
    resignation_date: document.getElementById('empIsActive').checked ? getVal('empResignationDate') : '',
    is_resigning: document.getElementById('empIsResigning').checked ? '1' : '0',
    severance_leave: parseFloat(getVal('empSeveranceLeave')) || 0,
    visible_to_exec: document.getElementById('empVisibleToExec').checked ? '1' : '0'
};

            const pw = getVal('empPassword');
            if (pw) data.password = pw;

            try {
                if (id) {
                    data.id = id;
                    await api.employees.update(data);
                } else {
                    await api.employees.create(data);
                }
                closeEmployeeModal();
                await loadEmployeesWithLeave();
                await loadResignedEmployees();
                await loadResigningEmployees();
                loadAllRequests();
                alert('저장되었습니다.');
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        function toggleResidentNo() {
            const el = document.getElementById('empResidentNo');
            if (!el) return;
            if (el.type === 'password') {
                document.getElementById('pwVerifyInput').value = '';
                document.getElementById('pwVerifyError').style.display = 'none';
                document.getElementById('passwordVerifyModal').classList.remove('hidden');
                document.getElementById('pwVerifyInput').focus();
            } else {
                el.type = 'password';
                el.value = '********';
                el.readOnly = true;
                const toggle = document.getElementById('residentNoToggle');
                if (toggle) toggle.textContent = '🔒';
            }
        }

        function closePasswordVerifyModal() {
            document.getElementById('passwordVerifyModal').classList.add('hidden');
        }

        function togglePwVerifyVisible() {
            const input = document.getElementById('pwVerifyInput');
            const toggle = document.getElementById('pwVerifyToggle');
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '🙈';
            } else {
                input.type = 'password';
                toggle.textContent = '👁️';
            }
        }

        async function confirmPasswordVerify() {
            const pw = document.getElementById('pwVerifyInput').value;
            if (!pw) {
                document.getElementById('pwVerifyError').textContent = '비밀번호를 입력하세요.';
                document.getElementById('pwVerifyError').style.display = 'block';
                return;
            }
            try {
                const res = await api.auth.verifyPassword(pw);
                if (res.success) {
                    closePasswordVerifyModal();
                    const el = document.getElementById('empResidentNo');
                    el.type = 'text';
                    el.value = el.dataset.original || '';
                    el.readOnly = false;
                    document.getElementById('residentNoToggle').textContent = '🔓';
                }
            } catch (err) {
                document.getElementById('pwVerifyError').textContent = '비밀번호가 올바르지 않습니다.';
                document.getElementById('pwVerifyError').style.display = 'block';
                document.getElementById('pwVerifyInput').value = '';
                document.getElementById('pwVerifyInput').focus();
            }
        }

        async function deleteEmployee(id) {
            if (!confirm('정말로 삭제하시겠습니까?')) return;
            
            try {
                await api.employees.delete(id);
                const currentTab = document.querySelector('.tab.active');
                if (currentTab && currentTab.textContent.includes('퇴사자')) {
                    loadResignedEmployees();
                } else {
                    loadEmployeesWithLeave();
                }
                alert('삭제되었습니다.');
            } catch (err) {
                alert(err.message);
            }
        }

        async function loadVacationTypes() {
            const res = await api.vacationTypes.list();
            vacationTypes = res.data;
            renderVacationTypes();
        }

        function renderVacationTypes() {
            const deductNames = { 'none': '차감없음', 'annual': '연차' };
            const len = vacationTypes.length;

            document.getElementById('typesList').innerHTML = vacationTypes.map((t, i) => `
                <tr>
                    <td>${t.sort_order}</td>
                    <td><span class="color-dot" style="background:${t.color}"></span>${t.name}</td>
                    <td>${t.deduction}</td>
                    <td>${t.max_days >= 999 ? '무제한' : t.max_days}</td>
                    <td>${deductNames[t.deduct_from]}</td>
                    <td>${t.count_all_days == 1 ? '포함' : '제외'}</td>
                    <td><input type="color" value="${t.color}" disabled></td>
                    <td style="white-space:nowrap">
                        <button class="btn btn-sm btn-secondary" onclick="moveType(${t.id}, -1)" ${i === 0 ? 'disabled' : ''}>▲</button>
                        <button class="btn btn-sm btn-secondary" onclick="moveType(${t.id}, 1)" ${i === len - 1 ? 'disabled' : ''}>▼</button>
                        <button class="btn btn-sm btn-secondary" onclick="editType(${t.id})">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteType(${t.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }
        
        async function moveType(id, direction) {
            const idx = vacationTypes.findIndex(t => t.id === id);
            if (idx === -1) return;
            const target = idx + direction;
            if (target < 0 || target >= vacationTypes.length) return;

            const ids = vacationTypes.map(t => t.id);
            [ids[idx], ids[target]] = [ids[target], ids[idx]];

            try {
                await api.vacationTypes.reorder(ids);
                await loadVacationTypes();
            } catch (err) {
                alert(err.message);
            }
        }

        async function deleteType(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            try {
                await api.vacationTypes.delete(id);
                alert('삭제되었습니다.');
                loadVacationTypes();
            } catch (err) {
                alert(err.message);
            }
        }

        function showTypeModal() {
            document.getElementById('typeModal').classList.remove('hidden');
            document.getElementById('typeForm').reset();
            document.getElementById('typeId').value = '';
            document.getElementById('typeModalTitle').textContent = '휴가 유형 추가';
        }

        function closeTypeModal() {
            document.getElementById('typeModal').classList.add('hidden');
        }

        function editType(id) {
            const type = vacationTypes.find(t => t.id === id);
            if (!type) return;

            document.getElementById('typeModal').classList.remove('hidden');
            document.getElementById('typeModalTitle').textContent = '휴가 유형 수정';
            document.getElementById('typeId').value = type.id;
            document.getElementById('typeName').value = type.name;
            document.getElementById('typeDeduction').value = type.deduction;
            document.getElementById('typeMax').value = type.max_days;
            document.getElementById('typeDeductFrom').value = type.deduct_from;
            document.getElementById('typeColor').value = type.color;
            document.getElementById('typeCountAllDays').checked = type.count_all_days == 1;
        }

        async function saveType(e) {
            e.preventDefault();
            
            const id = document.getElementById('typeId').value;
            const data = {
                name: document.getElementById('typeName').value,
                deduction: document.getElementById('typeDeduction').value,
                max_days: document.getElementById('typeMax').value,
                deduct_from: document.getElementById('typeDeductFrom').value,
                color: document.getElementById('typeColor').value,
                count_all_days: document.getElementById('typeCountAllDays').checked ? 1 : 0
            };

            try {
                if (id) {
                    data.id = id;
                    await api.vacationTypes.update(data);
                } else {
                    await api.vacationTypes.create(data);
                }
                closeTypeModal();
                loadVacationTypes();
                alert('저장되었습니다.');
            } catch (err) {
                alert(err.message);
            }
        }

        async function loadAllRequests() {
            const res = await api.vacationRequests.list();
            allRequests = res.data;
            renderAllRequests();
        }

        async function loadPositions() {
            try {
                const res = await api.positions.list();
                positions = res.data || [];
                populatePositionSelect();
                renderPositions();
            } catch (err) {
                console.error('Load positions error:', err);
            }
        }

        function populatePositionSelect() {
            const select = document.getElementById('empPosition');
            select.innerHTML = '<option value="">선택하세요</option>' + 
                positions.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        }

        function renderPositions() {
            document.getElementById('positionsList').innerHTML = positions.map(p => `
                <tr>
                    <td>${p.name}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editPosition(${p.id})">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deletePosition(${p.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }

        function showPositionModal(id = null, name = '') {
            document.getElementById('positionId').value = id || '';
            document.getElementById('positionName').value = name;
            document.getElementById('positionModalTitle').textContent = id ? '직급 수정' : '직급 추가';
            document.getElementById('positionModal').classList.remove('hidden');
        }

        function closePositionModal() {
            document.getElementById('positionModal').classList.add('hidden');
        }

        async function savePosition(e) {
            e.preventDefault();
            const id = document.getElementById('positionId').value;
            const name = document.getElementById('positionName').value;
            
            try {
                const res = id
                    ? await api.positions.update({ id, name })
                    : await api.positions.create({ name });
                
                if (res.success) {
                    alert('저장되었습니다.');
                    closePositionModal();
                    loadPositions();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function deletePosition(id) {
            if (!confirm('정말 삭제하시겠습니까?\n해당 직급을 사용 중인 사원의 직급은 초기화됩니다.')) return;
            
            try {
                const res = await api.positions.delete(id);
                if (res.success) {
                    alert('삭제되었습니다.');
                    loadPositions();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        function editPosition(id) {
            const pos = positions.find(p => p.id == id);
            if (pos) showPositionModal(pos.id, pos.name);
        }

        function showDepartmentModal(id = null, code = '', name = '', color = '') {
            document.getElementById('deptId').value = id || '';
            document.getElementById('deptCode').value = code;
            document.getElementById('deptName').value = name;
            document.getElementById('deptColor').value = color || '#667eea';
            document.getElementById('departmentModalTitle').textContent = id ? '부서 수정' : '부서 추가';
            document.getElementById('departmentModal').classList.remove('hidden');
        }

        function closeDepartmentModal() {
            document.getElementById('departmentModal').classList.add('hidden');
        }

        function renderDepartments() {
            document.getElementById('departmentsList').innerHTML = departments.map(d => `
                <tr>
                    <td>${d.code}</td>
                    <td>${d.name}</td>
                    <td><span class="color-dot" style="background:${d.color}"></span>${d.color}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editDepartment(${d.id})">수정</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteDepartment(${d.id})">삭제</button>
                    </td>
                </tr>
            `).join('');
        }

        function editDepartment(id) {
            const dept = departments.find(d => d.id == id);
            if (dept) showDepartmentModal(dept.id, dept.code, dept.name, dept.color);
        }

        async function saveDepartment(e) {
            e.preventDefault();
            const id = document.getElementById('deptId').value;
            const data = {
                code: document.getElementById('deptCode').value,
                name: document.getElementById('deptName').value,
                color: document.getElementById('deptColor').value
            };

            try {
                if (id) {
                    data.id = id;
                    await api.employees.updateDepartment(data);
                } else {
                    await api.employees.createDepartment(data);
                }
                closeDepartmentModal();
                await loadDepartments();
                await loadEmployeesWithLeave();
                alert('저장되었습니다.');
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        async function deleteDepartment(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            try {
                await api.employees.deleteDepartment(id);
                await loadDepartments();
                await loadEmployeesWithLeave();
                alert('삭제되었습니다.');
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        function renderAllRequests() {
            const statusNames = { applied: '신청', approved: '승인', cancelled: '취소' };

            document.getElementById('allRequestsList').innerHTML = allRequests.map(r => `
                <tr>
                    <td>${r.employee_name}</td>
                    <td>${r.department_name || '-'}</td>
                    <td>${r.start_date} ~ ${r.end_date}</td>
                    <td>${r.vacation_type_name}</td>
                    <td>${r.days}일</td>
                    <td>${(r.reason || '').substring(0, 20)}${r.reason && r.reason.length > 20 ? '...' : ''}</td>
                    <td><span class="status-badge status-${r.status}">${statusNames[r.status]}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="window.open('print.php?id=${r.id}', '_blank')">출력</button>
                    </td>
                </tr>
            `).join('');
        }

        function initHolidayYearSelect() {
            const select = document.getElementById('holidayYear');
            if (!select) return;
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            let html = '';
            for (let y = nextYear; y >= currentYear - 5; y--) {
                html += `<option value="${y}">${y}년</option>`;
            }
            select.innerHTML = html;
            select.value = currentYear;
        }

        function initAnnualYearSelect() {
            const select = document.getElementById('annualYearSelect');
            if (!select) return;
            const currentYear = new Date().getFullYear();
            const nextYear = currentYear + 1;
            let html = '';
            for (let y = nextYear; y >= currentYear - 5; y--) {
                html += `<option value="${y}">${y}년</option>`;
            }
            select.innerHTML = html;
            select.value = currentYear;
        }

        async function loadAnnualLeaveTable() {
            const year = document.getElementById('annualYearSelect')?.value;
            const tbody = document.getElementById('annualLeaveTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="9" class="loading"><div class="spinner"></div></td></tr>';

            try {
                const res = await fetch('api/vacation_requests.php?action=annual_list&year=' + year);
                const data = await res.json();
                annualLeaveData = data.data || [];
                renderAnnualLeaveTable(annualLeaveData);
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:red;">데이터 로드 실패</td></tr>';
            }
        }

        function renderAnnualLeaveTable(data) {
            const tbody = document.getElementById('annualLeaveTableBody');
            if (!tbody) return;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">데이터가 없습니다.</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(emp => {
                const remaining = emp.remaining;
                const remainingClass = remaining < 0 ? 'color:#dc2626;font-weight:700;' :
                                      remaining <= 3 ? 'color:#f59e0b;font-weight:600;' :
                                      'color:#16a34a;';
                return `<tr>
                    <td>${emp.name}</td>
                    <td style="color:#64748b;font-size:13px;">${emp.emp_no}</td>
                    <td>${emp.hire_date || '-'}</td>
                    <td>${emp.department_name || '-'}</td>
                    <td>${emp.position_name || '-'}</td>
                    <td style="text-align:center;">${emp.granted.toFixed(1)}</td>
                    <td style="text-align:center;">${emp.used.toFixed(1)}</td>
                    <td style="text-align:center;${remainingClass}">${remaining.toFixed(1)}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="editAnnualLeave(${emp.id})">수정</button>
                    </td>
                </tr>`;
            }).join('');
        }

        function editAnnualLeave(empId) {
            const year = document.getElementById('annualYearSelect').value;
            const emp = annualLeaveData.find(e => e.id === empId);
            if (!emp) return;

            document.getElementById('aleEmployeeId').value = emp.id;
            document.getElementById('aleYear').value = year;
            document.getElementById('aleEmployeeInfo').innerHTML =
                `<strong>${emp.name}</strong> (${emp.emp_no}) - ${emp.department_name || '-'} · ${emp.position_name || '-'}<br><small style="color:#64748b;">${year}년 부여연차를 수정합니다.</small>`;
            document.getElementById('aleGranted').value = emp.granted;
            document.getElementById('annualLeaveEditModal').classList.remove('hidden');
        }

        function closeAnnualLeaveEditModal() {
            document.getElementById('annualLeaveEditModal').classList.add('hidden');
        }

        async function saveAnnualLeaveEdit() {
            const empId = document.getElementById('aleEmployeeId').value;
            const year = document.getElementById('aleYear').value;
            const granted = document.getElementById('aleGranted').value;

            if (!empId || !year || !granted) {
                alert('값을 입력해주세요.');
                return;
            }

            try {
                const res = await api.vacationRequests.annualUpdate({ employee_id: empId, year, annual_leave: granted });
                if (res.success) {
                    alert('저장되었습니다.');
                    closeAnnualLeaveEditModal();
                    loadAnnualLeaveTable();
                } else {
                    alert('오류: ' + (res.error || '알 수 없는 오류'));
                }
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        // Init annual year select on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            initHolidayYearSelect();
            initAnnualYearSelect();
            const toggleBtn = document.getElementById('residentNoToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', toggleResidentNo);
            }
        });

        function logout() {
            if (confirm('로그아웃하시겠습니까?')) {
                location.href = 'api/auth.php?action=logout';
            }
        }

        // ── Certificate Requests ──
        async function loadCertificates() {
            try {
                const res = await api.certificate.list();
                renderCertificates(res.data || []);
            } catch (err) {
                document.getElementById('certificateList').innerHTML = '<tr><td colspan="11" style="text-align:center;color:#dc2626;">불러오기 실패</td></tr>';
            }
        }

        function renderCertificates(list) {
            const tbody = document.getElementById('certificateList');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;">요청 내역이 없습니다.</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(r => `
                <tr>
                    <td>${r.name} (${r.emp_no})</td>
                    <td>${r.department_name || '-'}</td>
                    <td>${r.certificate_type_label}</td>
                    <td>${r.show_resident_label}</td>
                    <td>${r.show_discipline_label}</td>
                    <td title="${r.job_desc_content ? r.job_desc_content.replace(/"/g, '&quot;') : ''}">${r.job_desc_label}</td>
                    <td>${r.job_desc_lang_label}</td>
                    <td>${r.created_at}</td>
                    <td><span class="status-badge status-${r.status === 'requested' ? 'applied' : 'approved'}">${r.status_label}</span></td>
                    <td>${r.notes || '-'}</td>
                    <td>
                        ${r.status === 'requested'
                            ? `<button class="btn btn-sm btn-primary" onclick="openCertCompleteModal(${r.id})">완료</button>`
                            : ''}
                    </td>
                </tr>
            `).join('');
        }

        function openCertCompleteModal(id) {
            document.getElementById('certCompleteId').value = id;
            document.getElementById('certCompleteNotes').value = '';
            document.getElementById('certCompleteModal').classList.remove('hidden');
        }

        function closeCertCompleteModal() {
            document.getElementById('certCompleteModal').classList.add('hidden');
        }

        async function saveCertComplete() {
            const id = document.getElementById('certCompleteId').value;
            const notes = document.getElementById('certCompleteNotes').value;
            try {
                await api.certificate.complete({ id, notes });
                closeCertCompleteModal();
                loadCertificates();
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        // ── Support Requests ──
        async function loadSupportRequests() {
            try {
                const res = await api.support.list();
                renderSupportList(res.data || []);
            } catch (err) {
                document.getElementById('supportList').innerHTML = '<tr><td colspan="8" style="text-align:center;color:#dc2626;">불러오기 실패</td></tr>';
            }
        }

        function renderSupportList(list) {
            const tbody = document.getElementById('supportList');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">요청 내역이 없습니다.</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(r => `
                <tr>
                    <td>${r.name} (${r.emp_no})</td>
                    <td>${r.department_name || '-'}</td>
                    <td>${r.request_type_label}</td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(r.content || '')}">${r.content || '-'}</td>
                    <td>${r.created_at}</td>
                    <td><span class="status-badge status-${r.status === 'requested' ? 'applied' : 'approved'}">${r.status_label}</span></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(r.notes || '')}">${r.notes || '-'}</td>
                    <td>
                        ${r.status === 'requested'
                            ? `<button class="btn btn-sm btn-primary" onclick="openSupportCompleteModal(${r.id})">완료</button>`
                            : ''}
                    </td>
                </tr>
            `).join('');
        }

        function openSupportCompleteModal(id) {
            document.getElementById('supportCompleteId').value = id;
            document.getElementById('supportCompleteStatus').value = 'completed';
            document.getElementById('supportCompleteNotes').value = '';
            document.getElementById('supportCompleteModal').classList.remove('hidden');
        }

        function closeSupportCompleteModal() {
            document.getElementById('supportCompleteModal').classList.add('hidden');
        }

        async function saveSupportComplete() {
            const id = document.getElementById('supportCompleteId').value;
            const status = document.getElementById('supportCompleteStatus').value;
            const notes = document.getElementById('supportCompleteNotes').value;
            try {
                await api.support.complete({ id, status, notes });
                closeSupportCompleteModal();
                loadSupportRequests();
            } catch (err) {
                alert('오류: ' + err.message);
            }
        }

        // ── SMTP Settings ──
        async function loadSmtpSettings() {
            try {
                const res = await api.settings.get();
                const data = res.data || {};
                document.getElementById('smtpHost').value = data.smtp_host || '';
                document.getElementById('smtpPort').value = data.smtp_port || '587';
                document.getElementById('smtpUser').value = data.smtp_user || '';
                document.getElementById('smtpPass').value = data.smtp_pass || '';
                document.getElementById('smtpEncryption').value = data.smtp_encryption || 'tls';
                document.getElementById('smtpFromEmail').value = data.smtp_from_email || '';
                document.getElementById('smtpAuth').checked = data.smtp_auth === '1';
            } catch (err) {
                document.getElementById('smtpResult').textContent = '설정 불러오기 실패';
                document.getElementById('smtpResult').style.color = '#dc2626';
            }
        }

        async function saveSmtpSettings() {
            const data = {
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: document.getElementById('smtpPort').value,
                smtp_user: document.getElementById('smtpUser').value,
                smtp_pass: document.getElementById('smtpPass').value,
                smtp_encryption: document.getElementById('smtpEncryption').value,
                smtp_from_email: document.getElementById('smtpFromEmail').value,
                smtp_auth: document.getElementById('smtpAuth').checked ? '1' : '0'
            };
            try {
                await api.settings.save(data);
                document.getElementById('smtpResult').textContent = '저장되었습니다.';
                document.getElementById('smtpResult').style.color = '#16a34a';
            } catch (err) {
                document.getElementById('smtpResult').textContent = '저장 실패: ' + err.message;
                document.getElementById('smtpResult').style.color = '#dc2626';
            }
        }

        async function testSmtpSettings() {
            const resultEl = document.getElementById('smtpResult');
            resultEl.textContent = '테스트 발송 중...';
            resultEl.style.color = '#666';
            try {
                const res = await api.request('certificate.php?action=test_email');
                if (res.success) {
                    resultEl.innerHTML = '✅ 테스트 이메일이 발송되었습니다.';
                    resultEl.style.color = '#16a34a';
                } else {
                    let msg = res.error || '알 수 없는 오류';
                    if (res.debug) {
                        msg += '<br><br><strong>SMTP 디버그 로그:</strong><br><pre style="background:#f5f5f5;padding:10px;border-radius:4px;font-size:12px;max-height:300px;overflow:auto;white-space:pre-wrap;word-break:break-all;margin-top:8px;">' + escapeHtml(res.debug) + '</pre>';
                    }
                    resultEl.innerHTML = '❌ ' + msg;
                    resultEl.style.color = '#dc2626';
                }
            } catch (err) {
                resultEl.innerHTML = '❌ 발송 실패: ' + escapeHtml(err.message);
                resultEl.style.color = '#dc2626';
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
