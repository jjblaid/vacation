const API_BASE = 'api';
let csrfToken = null;

const api = {
    setCsrfToken(token) {
        csrfToken = token;
    },

    async request(url, options = {}) {
        try {
            const hasBody = options.method && options.method !== 'GET';
            let finalOptions = { ...options, credentials: 'include' };
            
            if (hasBody && csrfToken) {
                if (finalOptions.body instanceof URLSearchParams) {
                    finalOptions.body = new URLSearchParams([...finalOptions.body, ['csrf_token', csrfToken]]);
                } else if (typeof finalOptions.body === 'string') {
                    try {
                        const parsed = JSON.parse(finalOptions.body);
                        parsed.csrf_token = csrfToken;
                        finalOptions.body = JSON.stringify(parsed);
                    } catch {
                        finalOptions.body += `&csrf_token=${encodeURIComponent(csrfToken)}`;
                    }
                }
            }
            
            const response = await fetch(`${API_BASE}/${url}`, finalOptions);
            const text = await response.text();
            
            if (!text) {
                throw new Error('서버 응답이 없습니다.');
            }
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('서버 응답 오류: ' + text.substring(0, 100));
            }
            
            if (!response.ok) {
                throw new Error(data.error || '요청 실패');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    auth: {
        login(empNo, password) {
            return api.request('auth.php?action=login', {
                method: 'POST',
                body: JSON.stringify({ emp_no: empNo, password }),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        logout() {
            return api.request('auth.php?action=logout', { method: 'POST' });
        },
        check() {
            return api.request('auth.php?action=check');
        },
        changePassword(currentPassword, newPassword, confirmPassword) {
            return api.request('auth.php?action=change_password', {
                method: 'POST',
                body: JSON.stringify({ current_password: currentPassword, new_password: newPassword, confirm_password: confirmPassword }),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        verifyPassword(password) {
            return api.request('auth.php?action=verify_password', {
                method: 'POST',
                body: JSON.stringify({ password }),
                headers: { 'Content-Type': 'application/json' }
            });
        }
    },
    
employees: {
         list(params) {
             let url = 'employees.php?action=list';
             if (params) {
                 const queryParams = new URLSearchParams();
                 for (const [key, value] of Object.entries(params)) {
                     if (value !== undefined && value !== null) {
                         queryParams.append(key, value);
                     }
                 }
                 if (queryParams.toString()) {
                     url += `&${queryParams.toString()}`;
                 }
             }
             return api.request(url);
         },
         get(id) {
             return api.request(`employees.php?action=get&id=${id}`);
         },
         create(data) {
             return api.request('employees.php?action=create', {
                 method: 'POST',
                 body: new URLSearchParams(data)
             });
         },
         update(data) {
             return api.request('employees.php?action=update', {
                 method: 'POST',
                 body: new URLSearchParams(data)
             });
         },
         delete(id) {
             return api.request('employees.php?action=delete', {
                 method: 'POST',
                 body: new URLSearchParams({ id })
             });
         },
         getDepartments() {
             return api.request('employees.php?action=departments');
         },
         createDepartment(data) {
             return api.request('employees.php?action=department_create', {
                 method: 'POST',
                 body: JSON.stringify(data),
                 headers: { 'Content-Type': 'application/json' }
             });
         },
         updateDepartment(data) {
             return api.request('employees.php?action=department_update', {
                 method: 'POST',
                 body: JSON.stringify(data),
                 headers: { 'Content-Type': 'application/json' }
             });
         },
         deleteDepartment(id) {
             return api.request('employees.php?action=department_delete', {
                 method: 'POST',
                 body: JSON.stringify({ id }),
                 headers: { 'Content-Type': 'application/json' }
             });
         },
         severance_usage(employee_id, year) {
             let url = `employees.php?action=severance_usage&employee_id=${employee_id}`;
             if (year) {
                 url += `&year=${year}`;
             }
             return api.request(url);
         }
     },
    
    vacationTypes: {
        list() {
            return api.request('vacation_types.php?action=list');
        },
        get(id) {
            return api.request(`vacation_types.php?action=get&id=${id}`);
        },
        create(data) {
            return api.request('vacation_types.php?action=create', {
                method: 'POST',
                body: new URLSearchParams(data)
            });
        },
        update(data) {
            return api.request('vacation_types.php?action=update', {
                method: 'POST',
                body: new URLSearchParams(data)
            });
        },
        delete(id) {
            return api.request('vacation_types.php?action=delete', {
                method: 'POST',
                body: new URLSearchParams({ id })
            });
        },
        reorder(ids) {
            return api.request('vacation_types.php?action=reorder', {
                method: 'POST',
                body: JSON.stringify({ ids }),
                headers: { 'Content-Type': 'application/json' }
            });
        }
    },
    
    condolenceTypes: {
        list() {
            return api.request('condolence_types.php?action=list');
        },
        getUsed(typeId) {
            return api.request(`condolence_types.php?action=get_used&type_id=${typeId}`);
        }
    },
    
    vacationRequests: {
        list(year, month, empId, deptId, excludeCancelled = true) {
            let url = 'vacation_requests.php?action=list';
            if (year != null && year !== undefined && year !== '') url += `&year=${year}`;
            if (month != null && month !== undefined && month !== '') url += `&month=${month}`;
            if (empId != null && empId !== undefined && empId !== '') url += `&emp_id=${empId}`;
            if (deptId != null && deptId !== undefined && deptId !== '') url += `&dept_id=${deptId}`;
            url += `&exclude_cancelled=${excludeCancelled ? '1' : '0'}`;
            return api.request(url);
        },
        calendar(start, end) {
            return api.request(`vacation_requests.php?action=calendar&start=${start}&end=${end}`);
        },
        detail(id) {
            return api.request(`vacation_requests.php?action=detail&id=${id}`);
        },
        create(data) {
            return api.request('vacation_requests.php?action=create', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        update(data) {
            return api.request('vacation_requests.php?action=update', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        cancel(id) {
            return api.request('vacation_requests.php?action=cancel', {
                method: 'POST',
                body: JSON.stringify({ id }),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        approve(id) {
            return api.request('vacation_requests.php?action=approve', {
                method: 'POST',
                body: JSON.stringify({ id }),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        getMyAnnualLeave() {
            return api.request('vacation_requests.php?action=my_remaining');
        },
        getMyAnnualInfo(year) {
            const params = year ? `&year=${year}` : '';
            return api.request('vacation_requests.php?action=my_annual_info' + params);
        },
        employeeAnnualList(year) {
            const params = year ? `&year=${year}` : '';
            return api.request('vacation_requests.php?action=employee_annual_list' + params);
        },
        getMyCondolenceInfo() {
            return api.request('vacation_requests.php?action=my_condolence_info');
        },
        employeeLeaveList(year) {
            const params = year ? `&year=${year}` : '';
            return api.request('vacation_requests.php?action=employee_leave_list' + params);
        },
        annualUpdate(data) {
            return api.request('vacation_requests.php?action=annual_update', {
                method: 'POST',
                body: new URLSearchParams(data)
            });
        }
    },

    positions: {
        list() {
            return api.request('positions.php?action=list');
        },
        create(data) {
            return api.request('positions.php?action=create', {
                method: 'POST',
                body: new URLSearchParams(data)
            });
        },
        update(data) {
            return api.request('positions.php?action=update', {
                method: 'POST',
                body: new URLSearchParams(data)
            });
        },
        delete(id) {
            return api.request('positions.php?action=delete', {
                method: 'POST',
                body: new URLSearchParams({ id })
            });
        }
    },

    holidays: {
        list(year) {
            let url = 'vacation_requests.php?action=holidays';
            if (year) url += `&year=${year}`;
            return api.request(url);
        },
        save(data) {
            return api.request('vacation_requests.php?action=holiday_save', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        delete(id) {
            return api.request('vacation_requests.php?action=holiday_delete', {
                method: 'POST',
                body: JSON.stringify({ id }),
                headers: { 'Content-Type': 'application/json' }
            });
        }
    },

    certificate: {
        request(data) {
            return api.request('certificate.php?action=request', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        list() {
            return api.request('certificate.php?action=list');
        },
        complete(data) {
            return api.request('certificate.php?action=complete', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        }
    },

    support: {
        request(data) {
            return api.request('support.php?action=request', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        },
        list() {
            return api.request('support.php?action=list');
        },
        complete(data) {
            return api.request('support.php?action=complete', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        }
    },

    settings: {
        get() {
            return api.request('settings.php?action=get');
        },
        save(data) {
            return api.request('settings.php?action=save', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json' }
            });
        }
    }
};
