let currentUser = null;
let vacationTypes = [];
let condolenceTypes = [];
let holidays = [];

document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
});

async function checkAuth() {
    try {
        const res = await api.auth.check();
        if (res.success) {
            currentUser = res.user;
            if (res.csrf_token) api.setCsrfToken(res.csrf_token);
            showApp();
        } else {
            showLogin();
        }
    } catch (err) {
        showLogin();
    }
}

function showLogin() {
    document.getElementById('loginPage').classList.remove('hidden');
    document.getElementById('appPage').classList.add('hidden');
}

function showApp() {
    document.getElementById('loginPage').classList.add('hidden');
    document.getElementById('appPage').classList.remove('hidden');
    
    renderUserInfo();
    initYearSelect();
    initMonthSelect();
    renderDashboard();
    loadVacationTypes();
    loadHolidays();
    loadCalendarEvents();
    loadVacationList(null, null);
    
    const myOnlyLabel = document.getElementById('myOnlyLabel');
    if (myOnlyLabel) {
        if (['system_admin', 'reviewer', 'dept_manager', 'vice_president'].includes(currentUser.role)) {
            myOnlyLabel.style.display = 'flex';
        } else {
            myOnlyLabel.style.display = 'none';
        }
    }
    
    if (currentUser.role === 'system_admin') {
        document.getElementById('adminLink').classList.remove('hidden');
    } else {
        document.getElementById('adminLink').classList.add('hidden');
    }
    
    if (['system_admin', 'reviewer', 'dept_manager'].includes(currentUser.role)) {
        document.getElementById('annualLeaveBtn').classList.remove('hidden');
    } else {
        document.getElementById('annualLeaveBtn').classList.add('hidden');
    }
    
    if (['system_admin', 'reviewer'].includes(currentUser.role)) {
        loadDeptFilter();
    }
}

async function loadHolidays() {
    try {
        const currentYear = new Date().getFullYear();
        const res = await api.holidays.list(currentYear);
        holidays = res.data || [];
    } catch (err) {
        console.error('Load holidays error:', err);
        holidays = [];
    }
}

function initYearSelect() {
    const currentYear = new Date().getFullYear();
    const nextYear = currentYear + 1;
    const yearSelect = document.getElementById('filterYear');
    if (yearSelect) {
        let html = '';
        for (let y = nextYear; y >= currentYear - 5; y--) {
            const selected = y === currentYear ? 'selected' : '';
            html += `<option value="${y}" ${selected}>${y}년</option>`;
        }
        yearSelect.innerHTML = html;
    }
    
    loadEmpFilter();

    const annualYearSelect = document.getElementById('annualLeaveYear');
    if (annualYearSelect) {
        annualYearSelect.innerHTML = yearSelect.innerHTML;
    }
}

function initMonthSelect() {
    const monthSelect = document.getElementById('filterMonth');
    if (!monthSelect) {
        console.error('filterMonth element not found!');
        return;
    }
    let html = '<option value="">전체 월</option>';
    const monthNames = ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'];
    for (let m = 1; m <= 12; m++) {
        html += `<option value="${m}">${monthNames[m-1]}</option>`;
    }
    monthSelect.innerHTML = html;

}

async function loadDeptFilter() {
    const deptSelect = document.getElementById('filterDept');
    if (!deptSelect) return;
    
    try {
        const res = await api.employees.getDepartments();
        let departments = res.data || [];
        
        let html = '<option value="">전체 본부</option>';
        departments.forEach(d => {
            html += `<option value="${d.id}">${d.name}</option>`;
        });
        deptSelect.innerHTML = html;
        deptSelect.style.display = 'block';
    } catch (err) {
        console.error('Load departments error:', err);
    }
}

async function loadEmpFilter() {
    const empSelect = document.getElementById('filterEmp');
    if (!empSelect) return;
    
    try {
        const res = await api.employees.list({ active: 1 });
        let filtered = res.data.filter(e => e.emp_no !== 'admin');
        
        if (currentUser.role === 'dept_manager' && currentUser.managed_department_id) {
            filtered = filtered.filter(e => String(e.department_id) === String(currentUser.managed_department_id));
        }
        
        filtered.sort((a, b) => a.name.localeCompare(b.name, 'ko'));
        
        let html = '<option value="">전체 사원</option>';
        filtered.forEach(e => {
            html += `<option value="${e.id}">${e.name} (${e.emp_no})</option>`;
        });
        empSelect.innerHTML = html;
        
        if (currentUser.role === 'user') {
            empSelect.style.display = 'none';
        } else {
            empSelect.style.display = 'block';
        }
    } catch (err) {
        console.error('Load employees error:', err);
    }
}

async function filterByDept() {
    const myOnlyCheck = document.getElementById('showMyOnly');
    if (myOnlyCheck) myOnlyCheck.checked = false;

    const deptId = document.getElementById('filterDept')?.value;
    const empSelect = document.getElementById('filterEmp');
    
    if (empSelect) {
        if (deptId) {
            await filterEmpByDept(deptId);
        } else {
            await loadEmpFilter();
        }
    }
    
    filterByYear();
}

async function filterEmpByDept(deptId) {
    const empSelect = document.getElementById('filterEmp');
    if (!empSelect) return;
    
    try {
        const res = await api.employees.list({ active: 1 });
        let filtered = res.data.filter(e => e.emp_no !== 'admin' && String(e.department_id) === String(deptId));
        
        filtered.sort((a, b) => a.name.localeCompare(b.name, 'ko'));
        
        let html = '<option value="">전체 사원</option>';
        filtered.forEach(e => {
            html += `<option value="${e.id}">${e.name} (${e.emp_no})</option>`;
        });
        empSelect.innerHTML = html;
        empSelect.style.display = 'block';
    } catch (err) {
        console.error('Filter employees error:', err);
    }
}

function filterByEmp() {
    const myOnlyCheck = document.getElementById('showMyOnly');
    if (myOnlyCheck) myOnlyCheck.checked = false;
    filterByYear();
}

function filterByYear() {
    const year = document.getElementById('filterYear')?.value;
    const month = document.getElementById('filterMonth')?.value;
    const empId = document.getElementById('filterEmp')?.value;
    const showCancelled = document.getElementById('showCancelled')?.checked || false;
    const currentPage = 1;
    loadVacationList(year, month, currentPage, empId, null, showCancelled);
    renderDashboard(year);
    
    if (calendar && year) {
        const currentYear = new Date().getFullYear();
        let targetDate;
        if (parseInt(year) === currentYear) {
            targetDate = new Date();
        } else {
            targetDate = new Date(year + '-01-01');
        }
        calendar.gotoDate(targetDate);
    }
}

function toggleMyVacationOnly() {
    const isChecked = document.getElementById('showMyOnly').checked;
    const deptFilter = document.getElementById('filterDept');
    const empFilter = document.getElementById('filterEmp');

    if (isChecked) {
        if (deptFilter) { deptFilter.style.display = 'none'; deptFilter.value = ''; }
        if (empFilter) { empFilter.style.display = 'none'; empFilter.value = ''; }
    } else {
        if (deptFilter && ['system_admin', 'reviewer'].includes(currentUser.role)) {
            deptFilter.style.display = 'block';
        }
        if (empFilter && currentUser.role !== 'user') {
            empFilter.style.display = 'block';
        }
    }

    loadVacationList(null, null);
}

function renderUserInfo() {
    document.getElementById('userName').textContent = currentUser.name;
    
    const roleNames = {
        'system_admin': '시스템관리자',
        'reviewer': '검토자',
        'dept_manager': '관리자',
        'ceo': '대표이사',
        'vice_president': '부대표',
        'user': '사용자'
    };
    document.getElementById('userRole').textContent = roleNames[currentUser.role] || currentUser.role;
}

async function renderDashboard(year) {
    if (!year) {
        year = document.getElementById('filterYear')?.value || new Date().getFullYear();
    }
    
    try {
        const res = await api.vacationRequests.getMyAnnualInfo(year);
        const data = res.data;
        
        document.getElementById('annualLeave').textContent = data.remaining.toFixed(1);
        document.getElementById('usedLeave').textContent = data.used.toFixed(1);
        
        // Handle severance leave display for resigning employees
        const severanceCard = document.getElementById('severanceCard');
        if (severanceCard) {
            if (data.is_resigning) {
                severanceCard.classList.remove('hidden');
                document.getElementById('severanceLeave').textContent = data.severance_remaining.toFixed(1);
            } else {
                severanceCard.classList.add('hidden');
            }
        }
        
        currentUser.annual_leave = data.remaining;
        currentUser.is_resigning = data.is_resigning;
        currentUser.severance_remaining = data.severance_remaining;
        
        const btnPrint = document.getElementById('btnPrintResign');
        if (btnPrint) {
            btnPrint.style.display = data.is_resigning ? 'inline-block' : 'none';
        }
        
        const listRes = await api.vacationRequests.list();
        const myRequests = listRes.data.filter(r => String(r.employee_id) === String(currentUser.id));
        const total = myRequests.filter(r => r.status !== 'cancelled').length;
        
        document.getElementById('totalRequests').textContent = total;
    } catch (err) {
        console.error('Dashboard error:', err);
    }
}

async function loadVacationTypes() {
    try {
        const res = await api.vacationTypes.list();
        vacationTypes = res.data;
        populateVacationTypes();
    } catch (err) {
        console.error('Failed to load vacation types:', err);
    }
}

async function loadVacationList(year, month, page, empId, deptId, showCancelled) {
    year = year || new Date().getFullYear();
    month = month || null;
    page = page || 1;
    if (showCancelled === undefined) {
        showCancelled = document.getElementById('showCancelled')?.checked || false;
    }
    if (!empId) {
        empId = document.getElementById('filterEmp')?.value;
    }
    if (!deptId) {
        deptId = document.getElementById('filterDept')?.value;
    }
    if (currentUser.role === 'user') {
        empId = currentUser.id;
    }
    const showMyOnly = document.getElementById('showMyOnly')?.checked;
    if (showMyOnly) {
        empId = currentUser.id;
    }
    const limit = 10;
    const offset = (page - 1) * limit;
    const showEmployeeInfo = ['system_admin', 'reviewer', 'dept_manager', 'ceo', 'vice_president'].includes(currentUser.role);
    
    const tbody = document.getElementById('vacationList');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="' + (showEmployeeInfo ? 7 : 5) + '" class="loading"><div class="spinner"></div></td></tr>';
    }
    
    try {
        const res = await api.vacationRequests.list(year, month, empId, deptId, !showCancelled);
        let data = res.data || [];
        
        const total = data.length;
        const paginated = data.slice(offset, offset + limit);
        
        renderVacationTable(paginated);
        renderPagination(total, page, limit, year, month, empId, deptId, showCancelled);
    } catch (err) {
        console.error('Failed to load vacation list:', err);
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="' + (showEmployeeInfo ? 7 : 5) + '" style="text-align:center;color:red;">데이터 로드 실패: ' + err.message + '</td></tr>';
        }
    }
}

function renderPagination(total, page, limit, year, month, empId, deptId, showCancelled) {
    const totalPages = Math.ceil(total / limit);
    
    const existing = document.getElementById('pagination');
    if (existing) existing.remove();
    
    if (totalPages <= 1) return;
    
    const empParam = empId ? `'${empId}'` : 'null';
    const deptParam = deptId ? `'${deptId}'` : 'null';
    const monthParam = (month !== undefined && month !== null) ? `${month}` : 'null';
    const cancelledParam = showCancelled ? 'true' : 'false';
    let html = '<div style="display:flex; justify-content:center; gap:8px; margin-top:16px;">';
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="btn btn-sm ${i === page ? 'btn-primary' : 'btn-secondary'}" onclick="loadVacationList(${year}, ${monthParam}, ${i}, ${empParam}, ${deptParam}, ${cancelledParam})">${i}</button>`;
    }
    html += '</div>';
    
    const tbody = document.getElementById('vacationList');
    if (tbody) {
        const div = document.createElement('div');
        div.id = 'pagination';
        div.innerHTML = html;
        tbody.closest('.section-body').appendChild(div);
    }
}

function renderVacationTable(data) {
    const tbody = document.getElementById('vacationList');
    if (!tbody) return;
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="' + (showEmployeeInfo ? 7 : 5) + '" style="text-align:center;">휴가 신청 내역이 없습니다.</td></tr>';
        return;
    }
    
    const showEmployeeInfo = ['system_admin', 'reviewer', 'dept_manager', 'ceo', 'vice_president'].includes(currentUser.role);
    const canApprove = ['system_admin', 'reviewer'].includes(currentUser.role);
    const canEdit = ['user', 'dept_manager'].includes(currentUser.role);
    
    let headerHtml = '<tr>';
    if (showEmployeeInfo) headerHtml += '<th>사원</th><th>부서</th>';
    headerHtml += '<th>기간</th><th>유형</th><th>일수</th><th>상태</th><th>작업</th></tr>';
    
    document.querySelector('#vacationList').parentElement.querySelector('thead').innerHTML = headerHtml;
    
    tbody.innerHTML = data.map(r => {
        const statusNames = { applied: '신청', approved: '완료', cancelled: '취소' };
        const statusClass = `status-${r.status}`;
        const isOwnRequest = r.employee_id === currentUser.id;
        const isDeptMember = currentUser.role === 'dept_manager' && 
                             String(r.department_id) === String(currentUser.managed_department_id);
        
        let row = '<tr>';
        if (showEmployeeInfo) {
            const deptColor = r.department_color || '#94a3b8';
            row += `<td>${r.employee_name}</td>`;
            row += `<td><span class="color-dot" style="background:${deptColor}"></span>${r.department_name || '-'}</td>`;
        }
        row += `<td>${r.start_date} ~ ${r.end_date}</td>`;
        row += `<td>${r.vacation_type_name}</td>`;
        row += `<td>${r.days}일</td>`;
        row += `<td><span class="status-badge ${statusClass}">${statusNames[r.status]}</span></td>`;
        row += `<td class="actions">`;
        
        if (r.status === 'applied' && (isOwnRequest || isDeptMember)) {
            row += `<button class="btn btn-sm btn-secondary" onclick="editVacation(${r.id})">수정</button> `;
        }
        
        if (r.status === 'applied' && canApprove) {
            row += `<button class="btn btn-sm btn-success" onclick="approveRequest(${r.id})">완료</button> `;
        }
        
        if (r.status !== 'cancelled' && canApprove) {
            row += `<button class="btn btn-sm btn-secondary" onclick="cancelRequest(${r.id})">취소</button> `;
        }
        
        row += `<button class="btn btn-sm btn-primary" onclick="printRequest(${r.id})">출력</button>`;
        row += `</td></tr>`;
        
        return row;
    }).join('');
}

async function approveRequest(id) {
    if (!confirm('이 휴가를 완료(승인)하시겠습니까?')) return;
    
    try {
        await api.vacationRequests.approve(id);
        alert('완료되었습니다.');
        renderDashboard();
        loadCalendarEvents();
        loadVacationList(null, null);
    } catch (err) {
        alert(err.message);
    }
}

async function cancelRequest(id) {
    if (!confirm('정말로 이 휴가를 취소하시겠습니까?')) return;
    
    try {
        await api.vacationRequests.cancel(id);
        alert('취소되었습니다.');
        renderDashboard();
        loadCalendarEvents();
        loadVacationList(null, null);
    } catch (err) {
        alert(err.message);
    }
}

function printRequest(id) {
    window.open(`print.php?id=${id}`, 'printWindow', 'width=800,height=600,scrollbars=yes');
}

function toggleAnnualLeaveView() {
    const annualSection = document.getElementById('annualLeaveSection');
    const vacationSection = document.getElementById('vacationListSection');
    const dashboardSection = document.getElementById('dashboardSection');
    const calendarSection = document.getElementById('calendarSection');
    const btn = document.getElementById('annualLeaveBtn');
    const annualYear = document.getElementById('annualLeaveYear');

    if (annualSection.classList.contains('hidden')) {
        const filterYear = document.getElementById('filterYear')?.value;
        if (annualYear && filterYear) {
            annualYear.value = filterYear;
        }
        annualSection.classList.remove('hidden');
        vacationSection.classList.add('hidden');
        dashboardSection.classList.add('hidden');
        calendarSection.classList.add('hidden');
        btn.textContent = '휴가 관리';
        loadEmployeeAnnualList();
    } else {
        annualSection.classList.add('hidden');
        vacationSection.classList.remove('hidden');
        dashboardSection.classList.remove('hidden');
        calendarSection.classList.remove('hidden');
        btn.textContent = '연차 현황';
    }
}

async function loadEmployeeAnnualList(year) {
    if (!year) {
        year = document.getElementById('annualLeaveYear')?.value || new Date().getFullYear();
    }

    const tbody = document.getElementById('annualLeaveList');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="loading"><div class="spinner"></div></td></tr>';
    }

    try {
        const res = await api.vacationRequests.employeeAnnualList(year);
        const data = res.data || [];
        renderEmployeeAnnualTable(data);
    } catch (err) {
        console.error('Failed to load annual leave list:', err);
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">데이터 로드 실패: ' + err.message + '</td></tr>';
        }
    }
}

function renderEmployeeAnnualTable(data) {
    const tbody = document.getElementById('annualLeaveList');
    if (!tbody) return;

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">데이터가 없습니다.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(emp => {
        const remaining = emp.remaining;
        const remainingClass = remaining < 0 ? 'color: #dc2626; font-weight: 700;' :
                              remaining <= 3 ? 'color: #f59e0b; font-weight: 600;' :
                              'color: #16a34a;';

        return `<tr>
            <td>${emp.name}</td>
            <td style="color:#64748b;font-size:13px;">${emp.emp_no}</td>
            <td>${emp.department_name || '-'}</td>
            <td>${emp.position_name || '-'}</td>
            <td style="text-align:center;">${emp.granted.toFixed(1)}</td>
            <td style="text-align:center;">${emp.used.toFixed(1)}</td>
            <td style="text-align:center; ${remainingClass}">${remaining.toFixed(1)}</td>
        </tr>`;
    }).join('');
}

// ── Certificate Request ──
function requestCertificate(type) {
    const label = type === 'career' ? '경력증명서' : '재직증명서';
    document.getElementById('certModalTitle').textContent = label + ' 발급 요청';
    document.getElementById('certType').value = type;
    document.getElementById('certShowResident').checked = false;
    document.getElementById('certShowDiscipline').checked = false;
    document.getElementById('certJobDesc').checked = false;
    document.getElementById('certJobDescKorean').checked = false;
    document.getElementById('certJobDescEnglish').checked = false;
    document.getElementById('certJobDescContent').value = '';
    document.getElementById('certJobDescContentGroup').classList.add('hidden');
    const isCareer = type === 'career';
    document.getElementById('certDisciplineRow').style.display = isCareer ? '' : 'none';
    document.getElementById('certJobDescRow').style.display = isCareer ? '' : 'none';
    document.getElementById('certificateModal').classList.remove('hidden');
}

function closeCertificateModal() {
    document.getElementById('certificateModal').classList.add('hidden');
}

function toggleJobDescContent() {
    const checked = document.getElementById('certJobDesc').checked;
    const group = document.getElementById('certJobDescContentGroup');
    const textarea = document.getElementById('certJobDescContent');
    if (checked) {
        group.classList.remove('hidden');
    } else {
        group.classList.add('hidden');
        textarea.value = '';
    }
}

async function submitCertificate() {
    const type = document.getElementById('certType').value;
    if (!type) return;
    try {
        await api.certificate.request({
            certificate_type: type,
            show_resident: document.getElementById('certShowResident').checked ? 1 : 0,
            show_discipline: document.getElementById('certShowDiscipline').checked ? 1 : 0,
            job_desc: document.getElementById('certJobDesc').checked ? 1 : 0,
            job_desc_content: document.getElementById('certJobDescContent').value.trim(),
            job_desc_korean: document.getElementById('certJobDescKorean').checked ? 1 : 0,
            job_desc_english: document.getElementById('certJobDescEnglish').checked ? 1 : 0
        });
        closeCertificateModal();
        alert('증명서 발급이 요청되었습니다.\n관리자가 확인 후 처리해드립니다.');
    } catch (err) {
        alert('요청 실패: ' + err.message);
    }
}

function toggleSupportMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('supportMenu');
    if (menu) menu.classList.toggle('show');
}

document.addEventListener('click', () => {
    const menu = document.getElementById('supportMenu');
    if (menu) menu.classList.remove('show');
});

// ── Support Request ──
function openSupportModal(type, label) {
    document.getElementById('supportModalTitle').textContent = label + ' 요청';
    document.getElementById('supportType').value = type;
    document.getElementById('supportContent').value = '';
    const notice = document.getElementById('supportNotice');
    if (type === 'id_card' || type === 'business_card') {
        notice.textContent = '발급소요시간 : 일주일 정도 소요예정';
    } else if (type === 'office_supply') {
        notice.textContent = '용품지급 : 2~3일 소요예정';
    } else {
        notice.textContent = '';
    }
    document.getElementById('supportModal').classList.remove('hidden');
}

function closeSupportModal() {
    document.getElementById('supportModal').classList.add('hidden');
}

async function submitSupportRequest() {
    const type = document.getElementById('supportType').value;
    const content = document.getElementById('supportContent').value.trim();
    if (!type) return;
    if (!content) {
        alert('요청사항을 입력해주세요.');
        return;
    }
    try {
        await api.support.request({ request_type: type, content });
        closeSupportModal();
        alert('행정지원 요청이 접수되었습니다.\n관리자가 확인 후 처리해드립니다.');
    } catch (err) {
        alert('요청 실패: ' + err.message);
    }
}

async function showVacationModal() {
    document.getElementById('vacationModal').classList.remove('hidden');
    document.getElementById('vacationForm').reset();
    document.getElementById('days').textContent = '0';
    document.getElementById('remainingDays').textContent = currentUser.annual_leave ? currentUser.annual_leave.toFixed(1) : '0';
    const sevGroup = document.getElementById('severanceRemainingGroup');
    if (currentUser.is_resigning) {
        sevGroup.style.display = '';
        document.getElementById('severanceRemainingDays').textContent = currentUser.severance_remaining.toFixed(1);
    } else {
        sevGroup.style.display = 'none';
    }
    populateVacationTypes();
    
    try {
        const res = await api.condolenceTypes.list();
        condolenceTypes = res.data;
        populateCondolenceTypes();
    } catch (err) {
        console.error('Failed to load condolence types:', err);
    }
    
    document.getElementById('condolenceTypeGroup').style.display = 'none';
    document.getElementById('condolenceInfo').style.display = 'none';

    document.getElementById('phone1').value = currentUser.phone1 || '';
    document.getElementById('phone2').value = currentUser.phone2 || '';
}

function printResignation() {
    window.open('print_resignation.php', '_blank', 'width=800,height=900');
}

function closeVacationModal() {
    document.getElementById('vacationModal').classList.add('hidden');
}

function populateCondolenceTypes() {
    const select = document.getElementById('condolenceType');
    if (!select) return;
    select.innerHTML = '<option value="">선택하세요</option>' + 
        condolenceTypes.map(t => `<option value="${t.id}" data-limit="${t.limit_days}">${t.name}</option>`).join('');
}

async function onVacationTypeChange() {
    const typeId = document.getElementById('vacationType').value;
    const selectedType = vacationTypes.find(t => String(t.id) === String(typeId));
    
    if (selectedType && selectedType.name === '경조사') {
        document.getElementById('condolenceTypeGroup').style.display = 'block';
        document.getElementById('condolenceType').value = '';
        document.getElementById('condolenceInfo').style.display = 'none';
    } else {
        document.getElementById('condolenceTypeGroup').style.display = 'none';
        document.getElementById('condolenceInfo').style.display = 'none';
    }
    
    updateDays();
}

async function onCondolenceTypeChange() {
    const condolenceTypeId = document.getElementById('condolenceType').value;
    const selectedCondType = condolenceTypes.find(t => String(t.id) === String(condolenceTypeId));
    
    if (condolenceTypeId && selectedCondType) {
        const isSpouseBirth = selectedCondType.name.includes('배우자') && selectedCondType.name.includes('출산');
        
        if (isSpouseBirth) {
            document.getElementById('condolenceInfoDefault').style.display = 'none';
            document.getElementById('condolenceInfoSpouseBirth').style.display = 'block';
            
            try {
                const res = await api.vacationRequests.getMyCondolenceInfo();
                const data = res.data;
                if (data.is_spouse_birth && data.spouse_birth) {
                    if (data.spouse_birth.exhausted) {
                        document.getElementById('spouseBirthRound').textContent = '1/4차';
                        document.getElementById('spouseBirthRemaining').textContent = '20';
                    } else {
                        document.getElementById('spouseBirthRound').textContent = `${data.spouse_birth.current_round}/4차`;
                        document.getElementById('spouseBirthRemaining').textContent = data.spouse_birth.round_remaining.toFixed(1);
                    }
                }
            } catch (err) {
                document.getElementById('spouseBirthRound').textContent = '1/4차';
                document.getElementById('spouseBirthRemaining').textContent = '20';
            }
        } else {
            document.getElementById('condolenceInfoDefault').style.display = 'block';
            document.getElementById('condolenceInfoSpouseBirth').style.display = 'none';
            
            if (selectedCondType.limit_days) {
                document.getElementById('condolenceTotalDays').textContent = selectedCondType.limit_days;
                try {
                    const res = await api.condolenceTypes.getUsed(condolenceTypeId);
                    const used = res.data.used_days || 0;
                    const remaining = parseFloat(selectedCondType.limit_days) - used;
                    document.getElementById('condolenceRemainingDays').textContent = Math.max(0, remaining).toFixed(1);
                } catch (err) {
                    document.getElementById('condolenceRemainingDays').textContent = selectedCondType.limit_days;
                }
            }
        }
        
        document.getElementById('condolenceInfo').style.display = 'block';
    } else {
        document.getElementById('condolenceInfo').style.display = 'none';
    }
    
    updateDays();
}

async function submitVacation(e) {
    e.preventDefault();
    
    const typeId = document.getElementById('vacationType').value;
    const condolenceTypeId = document.getElementById('condolenceType').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const startHalf = document.getElementById('startHalf')?.value || 'full';
    const endHalf = document.getElementById('endHalf')?.value || 'full';
    const days = parseFloat(document.getElementById('days').textContent);
    const reason = document.getElementById('reason').value;
    const phone1 = document.getElementById('phone1').value;
    const phone2 = document.getElementById('phone2').value;
    
    const selectedType = vacationTypes.find(t => String(t.id) === String(typeId));
    
    if (!typeId || !startDate || !endDate || !reason) {
        alert('모든 필드를 입력해주세요.');
        return;
    }
    
    if (selectedType && selectedType.name === '경조사' && !condolenceTypeId) {
        alert('경조사 사유를 선택해주세요.');
        return;
    }
    
    try {
        await api.vacationRequests.create({
            vacation_type_id: typeId,
            condolence_type_id: condolenceTypeId || null,
            start_date: startDate,
            end_date: endDate,
            start_half: startHalf,
            end_half: endHalf,
            days: days,
            reason: reason,
            phone1: phone1,
            phone2: phone2
        });
        
        alert('휴가 신청이 완료되었습니다.');
        closeVacationModal();
        renderDashboard();
        loadCalendarEvents();
        loadVacationList(null, null);
    } catch (err) {
        alert(err.message);
    }
}

function updateDays() {
    const typeId = document.getElementById('vacationType').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const startHalf = document.getElementById('startHalf')?.value || 'full';
    const endHalf = document.getElementById('endHalf')?.value || 'full';
    
    const type = vacationTypes.find(t => t.id == typeId);
    
    if (type && (type.name.includes('반차'))) {
        document.getElementById('days').textContent = '0.5';
        document.getElementById('endDate').value = startDate;
        document.getElementById('endDate').disabled = true;
        document.getElementById('startHalfContainer')?.classList.add('hidden');
        document.getElementById('endHalfContainer')?.classList.add('hidden');
    } else {
        document.getElementById('endDate').disabled = false;
        document.getElementById('startHalfContainer')?.classList.remove('hidden');
        document.getElementById('endHalfContainer')?.classList.remove('hidden');
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            let days = 0;
            
            const startStr = start.toISOString().split('T')[0];
            const endStr = end.toISOString().split('T')[0];
            
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const day = d.getDay();
                const dateStr = d.toISOString().split('T')[0];
                const isHoliday = holidays.some(h => h.date === dateStr);
                const isWorkingDay = day !== 0 && day !== 6 && !isHoliday;
                
                if (type && (type.count_all_days == 1 || isWorkingDay)) {
                    if (dateStr === startStr && dateStr === endStr) {
                        days += startHalf === 'full' ? 1 : 0.5;
                    } else if (dateStr === startStr) {
                        if (startHalf === 'morning' || startHalf === 'afternoon') days += 0.5;
                        else days += 1;
                    } else if (dateStr === endStr) {
                        if (endHalf === 'morning' || endHalf === 'afternoon') days += 0.5;
                        else days += 1;
                    } else {
                        days += 1;
                    }
                }
            }
            
            document.getElementById('days').textContent = days;
        }
    }
    
    if (type) {
        document.getElementById('remainingDays').textContent = currentUser.annual_leave.toFixed(1);
    }
}

function populateVacationTypes() {
    const select = document.getElementById('vacationType');
    if (!select) return;
    select.innerHTML = '<option value="">선택하세요</option>' + 
        vacationTypes.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
}

function logout() {
    if (confirm('로그아웃하시겠습니까?')) {
        fetch('api/auth.php?action=logout', { method: 'POST' })
            .then(() => { window.location.href = 'index.php'; })
            .catch(() => { window.location.href = 'index.php'; });
    }
}

function showPasswordModal() {
    document.getElementById('passwordModal').classList.remove('hidden');
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.add('hidden');
}

async function changePassword(event) {
    if (event) event.preventDefault();
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('모든 필드를 입력해주세요.');
        return;
    }
    
    try {
        const res = await api.auth.changePassword(currentPassword, newPassword, confirmPassword);
        alert('비밀번호가 변경되었습니다.');
        closePasswordModal();
    } catch (err) {
        alert(err.message);
    }
}

async function editVacation(id) {
    try {
        if (condolenceTypes.length === 0) {
            const res = await api.condolenceTypes.list();
            condolenceTypes = res.data;
        }
        const res = await api.vacationRequests.detail(id);
        const data = res.data;
        
        document.getElementById('editRequestId').value = data.id;
        document.getElementById('editReason').value = data.reason;
        document.getElementById('editStartDate').value = data.start_date;
        document.getElementById('editEndDate').value = data.end_date;
        
        populateEditVacationTypes(data.vacation_type_id, data.condolence_type_id);
        
        updateEditDays();
        
        document.getElementById('vacationEditModal').classList.remove('hidden');
    } catch (err) {
        alert(err.message);
    }
}

function populateEditVacationTypes(selectedId, condolenceTypeId = null) {
    const select = document.getElementById('editVacationType');
    if (!select) return;
    select.innerHTML = '<option value="">선택하세요</option>' + 
        vacationTypes.map(t => `<option value="${t.id}" ${t.id == selectedId ? 'selected' : ''}>${t.name}</option>`).join('');
    
    const selectedType = vacationTypes.find(t => String(t.id) === String(selectedId));
    const condolenceGroup = document.getElementById('editCondolenceTypeGroup');
    const condolenceInfo = document.getElementById('editCondolenceInfo');
    const condolenceSelect = document.getElementById('editCondolenceType');
    
    if (selectedType && selectedType.name === '경조사') {
        condolenceSelect.innerHTML = '<option value="">선택하세요</option>' + 
            condolenceTypes.map(t => `<option value="${t.id}" data-limit="${t.limit_days}">${t.name}</option>`).join('');
        condolenceGroup.style.display = 'block';
        
        if (condolenceTypeId) {
            condolenceSelect.value = condolenceTypeId;
            const selected = condolenceTypes.find(t => String(t.id) === String(condolenceTypeId));
            if (selected) {
                document.getElementById('editCondolenceTotalDays').textContent = selected.limit_days;
                condolenceInfo.style.display = 'block';
            }
        } else {
            condolenceSelect.value = '';
            condolenceInfo.style.display = 'none';
        }
    } else {
        condolenceGroup.style.display = 'none';
        condolenceInfo.style.display = 'none';
    }
}

function closeVacationEditModal() {
    document.getElementById('vacationEditModal').classList.add('hidden');
}

function updateEditDays() {
    const startDate = document.getElementById('editStartDate').value;
    const endDate = document.getElementById('editEndDate').value;
    const typeId = document.getElementById('editVacationType').value;
    const startHalf = document.getElementById('editStartHalf')?.value || 'full';
    const endHalf = document.getElementById('editEndHalf')?.value || 'full';
    
    const type = vacationTypes.find(t => t.id == typeId);
    
    if (type && (type.name.includes('반차'))) {
        document.getElementById('editDays').textContent = '0.5';
        document.getElementById('editEndDate').value = startDate;
        document.getElementById('editEndDate').disabled = true;
        document.getElementById('editStartHalfContainer')?.classList.add('hidden');
        document.getElementById('editEndHalfContainer')?.classList.add('hidden');
    } else {
        document.getElementById('editEndDate').disabled = false;
        document.getElementById('editStartHalfContainer')?.classList.remove('hidden');
        document.getElementById('editEndHalfContainer')?.classList.remove('hidden');
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            let days = 0;
            
            const startStr = start.toISOString().split('T')[0];
            const endStr = end.toISOString().split('T')[0];
            
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const day = d.getDay();
                const dateStr = d.toISOString().split('T')[0];
                const isHoliday = holidays.some(h => h.date === dateStr);
                const isWorkingDay = day !== 0 && day !== 6 && !isHoliday;
                
                if (type && (type.count_all_days == 1 || isWorkingDay)) {
                    if (dateStr === startStr && dateStr === endStr) {
                        days += startHalf === 'full' ? 1 : 0.5;
                    } else if (dateStr === startStr) {
                        if (startHalf === 'morning' || startHalf === 'afternoon') days += 0.5;
                        else days += 1;
                    } else if (dateStr === endStr) {
                        if (endHalf === 'morning' || endHalf === 'afternoon') days += 0.5;
                        else days += 1;
                    } else {
                        days += 1;
                    }
                }
            }
            
            document.getElementById('editDays').textContent = days;
        }
    }
}

async function submitVacationEdit(e) {
    e.preventDefault();
    
    const id = document.getElementById('editRequestId').value;
    const typeId = document.getElementById('editVacationType').value;
    const condolenceTypeId = document.getElementById('editCondolenceType').value;
    const startDate = document.getElementById('editStartDate').value;
    const endDate = document.getElementById('editEndDate').value;
    const startHalf = document.getElementById('editStartHalf')?.value || 'full';
    const endHalf = document.getElementById('editEndHalf')?.value || 'full';
    const days = parseFloat(document.getElementById('editDays').textContent);
    const reason = document.getElementById('editReason').value;
    
    const selectedType = vacationTypes.find(t => String(t.id) === String(typeId));
    
    if (!typeId || !startDate || !endDate || !reason) {
        alert('모든 필드를 입력해주세요.');
        return;
    }
    
    if (selectedType && selectedType.name === '경조사' && !condolenceTypeId) {
        alert('경조사 사유를 선택해주세요.');
        return;
    }
    
    try {
        await api.vacationRequests.update({
            id: id,
            vacation_type_id: typeId,
            condolence_type_id: condolenceTypeId || null,
            start_date: startDate,
            end_date: endDate,
            start_half: startHalf,
            end_half: endHalf,
            days: days,
            reason: reason
        });
        
        alert('수정되었습니다.');
        closeVacationEditModal();
        renderDashboard();
        loadVacationList(null, null);
        loadCalendarEvents();
    } catch (err) {
        alert(err.message);
    }
}

document.getElementById('vacationType')?.addEventListener('change', () => {
    updateDays();
});

document.getElementById('startDate')?.addEventListener('change', updateDays);
document.getElementById('endDate')?.addEventListener('change', updateDays);
document.getElementById('startHalf')?.addEventListener('change', updateDays);
document.getElementById('endHalf')?.addEventListener('change', updateDays);

document.getElementById('editVacationType')?.addEventListener('change', () => {
    const typeId = document.getElementById('editVacationType').value;
    const selectedType = vacationTypes.find(t => String(t.id) === String(typeId));
    
    if (selectedType && selectedType.name === '경조사') {
        document.getElementById('editCondolenceTypeGroup').style.display = 'block';
        document.getElementById('editCondolenceType').innerHTML = '<option value="">선택하세요</option>' + 
            condolenceTypes.map(t => `<option value="${t.id}" data-limit="${t.limit_days}">${t.name}</option>`).join('');
        document.getElementById('editCondolenceInfo').style.display = 'none';
    } else {
        document.getElementById('editCondolenceTypeGroup').style.display = 'none';
        document.getElementById('editCondolenceInfo').style.display = 'none';
    }
    
    updateEditDays();
});

document.getElementById('editCondolenceType')?.addEventListener('change', async () => {
    const condolenceTypeId = document.getElementById('editCondolenceType').value;
    const selectedCondType = condolenceTypes.find(t => String(t.id) === String(condolenceTypeId));
    
    if (condolenceTypeId && selectedCondType) {
        const isSpouseBirth = selectedCondType.name.includes('배우자') && selectedCondType.name.includes('출산');
        
        if (isSpouseBirth) {
            document.getElementById('editCondolenceInfoDefault').style.display = 'none';
            document.getElementById('editCondolenceInfoSpouseBirth').style.display = 'block';
            
            try {
                const res = await api.vacationRequests.getMyCondolenceInfo();
                const data = res.data;
                if (data.is_spouse_birth && data.spouse_birth) {
                    if (data.spouse_birth.exhausted) {
                        document.getElementById('editSpouseBirthRound').textContent = '1/4차';
                        document.getElementById('editSpouseBirthRemaining').textContent = '20';
                    } else {
                        document.getElementById('editSpouseBirthRound').textContent = `${data.spouse_birth.current_round}/4차`;
                        document.getElementById('editSpouseBirthRemaining').textContent = data.spouse_birth.round_remaining.toFixed(1);
                    }
                }
            } catch (err) {
                document.getElementById('editSpouseBirthRound').textContent = '1/4차';
                document.getElementById('editSpouseBirthRemaining').textContent = '20';
            }
        } else {
            document.getElementById('editCondolenceInfoDefault').style.display = 'block';
            document.getElementById('editCondolenceInfoSpouseBirth').style.display = 'none';
            
            if (selectedCondType.limit_days) {
                document.getElementById('editCondolenceTotalDays').textContent = selectedCondType.limit_days;
                try {
                    const res = await api.condolenceTypes.getUsed(condolenceTypeId);
                    const used = res.data.used_days || 0;
                    const remaining = parseFloat(selectedCondType.limit_days) - used;
                    document.getElementById('editCondolenceRemainingDays').textContent = Math.max(0, remaining).toFixed(1);
                } catch (err) {
                    document.getElementById('editCondolenceRemainingDays').textContent = selectedCondType.limit_days;
                }
            }
        }
        
        document.getElementById('editCondolenceInfo').style.display = 'block';
    } else {
        document.getElementById('editCondolenceInfo').style.display = 'none';
    }
});
document.getElementById('editStartDate')?.addEventListener('change', updateEditDays);
document.getElementById('editEndDate')?.addEventListener('change', updateEditDays);
document.getElementById('editStartHalf')?.addEventListener('change', updateEditDays);
document.getElementById('editEndHalf')?.addEventListener('change', updateEditDays);

window.cancelRequest = cancelRequest;
window.approveRequest = approveRequest;
window.printRequest = printRequest;
window.printResignation = printResignation;
window.showVacationModal = showVacationModal;
window.closeVacationModal = closeVacationModal;
window.submitVacation = submitVacation;
window.logout = logout;
window.editVacation = editVacation;
window.closeVacationEditModal = closeVacationEditModal;
window.submitVacationEdit = submitVacationEdit;
window.onVacationTypeChange = onVacationTypeChange;
window.onCondolenceTypeChange = onCondolenceTypeChange;
window.toggleMyVacationOnly = toggleMyVacationOnly;

function addToFavorites() {
    alert('즐겨찾기에 추가하려면 Ctrl+D 를 눌러주세요.');
}
