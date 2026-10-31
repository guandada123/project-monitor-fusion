--
-- PostgreSQL database dump
--


-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: projects; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.projects (id, name, slug, source, external_id, owner_id, status, description, start_at, end_at, meta, tags, config, created_at, updated_at, deleted_at) FROM stdin;
3	StockInsight	stockinsight	ops_snapshot	StockInsight	\N	active	独立股票分析(桌面端)	2026-10-31	\N	{"code": {"todos": 0, "src_loc": 34749, "test_loc": 31407, "src_files": 138, "total_loc": 66156, "truncated": false, "test_files": 91}, "head": "2c25b16", "path": "/Volumes/ZHITAI/WorkBuddy/StockInsight", "repo": "StockInsight", "role": "独立股票分析(桌面端)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-30T23:51:20+08:00", "project": "StockInsight", "priority": "P2", "ci_status": "green", "ci_scanned": true, "commits_7d": 21, "ci_main_red": false, "dirty_files": 3, "path_exists": true, "head_subject": "chore(auto): 自动产物例行提交（2026-10-30）", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-10-30T15:51:41Z", "dirty_oldest_h": 11.3, "ci_red_branches": 0, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
6	wechat_api+wechat-download-api	wechat-api	ops_snapshot	wechat_api	\N	active	公众号抓取三件套(MCP入口+底层下载)	2026-10-31	\N	{"code": {"todos": 1, "src_loc": 9488, "test_loc": 0, "src_files": 39, "total_loc": 9488, "truncated": false, "test_files": 0}, "head": "2173596", "path": "~/WorkBuddy/wechat_api", "repo": "wechat_api", "role": "公众号抓取三件套(MCP入口+底层下载)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-24T11:45:28+08:00", "project": "wechat_api+wechat-download-api", "priority": "P1", "ci_status": "none", "ci_scanned": false, "commits_7d": 0, "ci_main_red": null, "dirty_files": 1, "path_exists": true, "head_subject": "fix(security): 端口绑定由 0.0.0.0 收紧到 127.0.0.1", "collect_error": null, "ledger_status": "active", "ci_last_run_at": null, "dirty_oldest_h": 11.4, "ci_red_branches": null, "same_declaration": true}	["P1"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
7	wechat_api+wechat-download-api	wechat-download-api	ops_snapshot	wechat-download-api	\N	active	公众号抓取三件套(MCP入口+底层下载)	2026-10-31	\N	{"code": null, "head": null, "path": "~/WorkBuddy/wechat-download-api", "repo": "wechat-download-api", "role": "公众号抓取三件套(MCP入口+底层下载)", "vibe": null, "branch": null, "is_git": false, "head_ts": null, "project": "wechat_api+wechat-download-api", "priority": "P1", "ci_status": "green", "ci_scanned": true, "commits_7d": null, "ci_main_red": false, "dirty_files": null, "path_exists": false, "head_subject": null, "collect_error": "not_a_git_repo", "ledger_status": "retired", "ci_last_run_at": "2026-10-24T03:46:53Z", "dirty_oldest_h": null, "ci_red_branches": 0, "same_declaration": true}	["P1"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
1	Claw	claw	ops_snapshot	Claw	\N	active	A股投顾自动化核心	2026-10-31	\N	{"code": {"todos": 31, "src_loc": 93774, "test_loc": 29906, "src_files": 364, "total_loc": 123680, "truncated": false, "test_files": 178}, "head": "f3c41cd", "path": "~/WorkBuddy/Claw", "repo": "Claw", "role": "A股投顾自动化核心", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-31T15:23:56+08:00", "project": "Claw", "priority": "P0", "ci_status": "green", "ci_scanned": true, "commits_7d": 135, "ci_main_red": false, "dirty_files": 1, "path_exists": true, "head_subject": "fix(memory): 余量守卫补「字符水位」—— 字节口径报 2222B 余量时字符只剩 103", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-10-31T07:24:05Z", "dirty_oldest_h": 1.9, "ci_red_branches": 0, "same_declaration": false}	["P0"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
9	ai-autonomy-team	ai-autonomy-team	ops_snapshot	ai-autonomy-team	\N	active	多角色AI评审的过程约束与验收工具集（零第三方依赖·纯标准库）	2026-10-31	\N	{"code": {"todos": 15, "src_loc": 29716, "test_loc": 2871, "src_files": 81, "total_loc": 32587, "truncated": false, "test_files": 3}, "head": "889dea7", "path": "/Volumes/ZHITAI/WorkBuddy/ai-autonomy-team", "repo": "ai-autonomy-team", "role": "多角色AI评审的过程约束与验收工具集（零第三方依赖·纯标准库）", "vibe": {"step": 3, "stage": "MVP 定义", "deadline": "2026-11-13", "mvp_done": 4, "available": true, "mvp_total": 4, "milestones": 7, "step_source": "scan_inferred", "total_steps": 14, "last_activity": "2026-10-31T15:29:24+08:00"}, "branch": "main", "is_git": true, "head_ts": "2026-10-31T15:29:55+08:00", "project": "ai-autonomy-team", "priority": "P2", "ci_status": "none", "ci_scanned": false, "commits_7d": 109, "ci_main_red": null, "dirty_files": 4, "path_exists": true, "head_subject": "test(caliber): M-004 外推盲验——5 新题 2 判者 5/5 一致（RESULT-m004-blind）", "collect_error": null, "ledger_status": "active", "ci_last_run_at": null, "dirty_oldest_h": 34.1, "ci_red_branches": null, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
8	engineering-audit-kit	engineering-audit-kit	ops_snapshot	engineering-audit-kit	\N	active	审计工具包(休眠)	2026-10-31	\N	{"code": {"todos": 0, "src_loc": 473, "test_loc": 0, "src_files": 3, "total_loc": 473, "truncated": false, "test_files": 0}, "head": "368f4d8", "path": "~/WorkBuddy/engineering-audit-kit", "repo": "engineering-audit-kit", "role": "审计工具包(休眠)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-24T11:45:26+08:00", "project": "engineering-audit-kit", "priority": "P3", "ci_status": "none", "ci_scanned": false, "commits_7d": 0, "ci_main_red": null, "dirty_files": 1, "path_exists": true, "head_subject": "chore(security): 补入本机敏感信息隔离规则（此前只在工作区，未生效）", "collect_error": null, "ledger_status": "dormant", "ci_last_run_at": null, "dirty_oldest_h": 11.4, "ci_red_branches": null, "same_declaration": false}	["P3"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
2	QuantTradingSystem	quanttradingsystem	ops_snapshot	QuantTradingSystem	\N	active	Docker 13容器量化系统	2026-10-31	\N	{"code": {"todos": 3, "src_loc": 45789, "test_loc": 43147, "src_files": 219, "total_loc": 88936, "truncated": false, "test_files": 97}, "head": "8cc5e34", "path": "~/WorkBuddy/QuantTradingSystem", "repo": "QuantTradingSystem", "role": "Docker 13容器量化系统", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-31T14:36:31+08:00", "project": "QuantTradingSystem", "priority": "P1", "ci_status": "green", "ci_scanned": true, "commits_7d": 11, "ci_main_red": false, "dirty_files": 4, "path_exists": true, "head_subject": "fix(compose): strategy-service 挂载 Claw 实时模拟盘数据", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-10-31T06:36:54Z", "dirty_oldest_h": 3.9, "ci_red_branches": 0, "same_declaration": false}	["P1"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
4	marvis_bridge	marvis-bridge	ops_snapshot	marvis_bridge	\N	active	Shell+Python桥接调度	2026-10-31	\N	{"code": {"todos": 3, "src_loc": 4778, "test_loc": 1117, "src_files": 24, "total_loc": 5895, "truncated": false, "test_files": 3}, "head": "3b77e74", "path": "~/WorkBuddy/marvis_bridge", "repo": "marvis_bridge", "role": "Shell+Python桥接调度", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-24T08:46:22+08:00", "project": "marvis_bridge", "priority": "P2", "ci_status": "none", "ci_scanned": false, "commits_7d": 0, "ci_main_red": null, "dirty_files": 0, "path_exists": true, "head_subject": "chore(archive): 补入下线前未入库的任务结果与完成记录（32 个）", "collect_error": null, "ledger_status": "retired", "ci_last_run_at": null, "dirty_oldest_h": null, "ci_red_branches": null, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
5	project-monitor-fusion	project-monitor-fusion	ops_snapshot	project-monitor-fusion	\N	active	Laravel监控面板(已融合ops-center, :8088)	2026-10-31	\N	{"code": {"todos": 1, "src_loc": 34531, "test_loc": 480, "src_files": 230, "total_loc": 35011, "truncated": false, "test_files": 3}, "head": "72cd605", "path": "~/WorkBuddy/project-monitor-fusion", "repo": "project-monitor-fusion", "role": "Laravel监控面板(已融合ops-center, :8088)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-31T15:22:45+08:00", "project": "project-monitor-fusion", "priority": "P2", "ci_status": "green", "ci_scanned": true, "commits_7d": 12, "ci_main_red": false, "dirty_files": 0, "path_exists": true, "head_subject": "fix(layout): header 补 z-40 —— 修通知面板被页面内容盖住", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-10-31T07:23:31Z", "dirty_oldest_h": null, "ci_red_branches": 1, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-10-31T18:29:52+08:00"}	2026-10-31 07:14:01	2026-10-31 10:30:00	\N
\.


--
-- Data for Name: tasks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tasks (id, project_id, external_id, title, description, assignee_id, status, priority, estimate, spent, start_at, due_at, completed_at, tags, meta, created_at, updated_at, deleted_at, sort_order) FROM stdin;
\.


--
-- Data for Name: assignments; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.assignments (id, task_id, user_id, role, allocation_percent, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: events; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.events (id, source, external_id, event_type, occurred_at, payload, fingerprint, processed_at, project_id, error, retry_count, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: export_history; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.export_history (id, user_id, entity_type, format, filters, selected_columns, record_count, file_path, file_size, status, error_message, completed_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: metrics; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.metrics (id, project_id, key, name, kind, definition, dimensions, filters, schedule_cron, last_computed_at, current_value, current_json_value, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: metric_snapshots; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.metric_snapshots (id, metric_id, captured_at, value, json_value, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_06_14_144729_create_projects_table	1
5	2026_06_14_144729_create_tasks_table	1
6	2026_06_14_144730_create_assignments_table	1
7	2026_06_14_144731_create_metrics_table	1
8	2026_06_14_144732_create_metric_snapshots_table	1
9	2026_06_14_144733_create_risks_table	1
10	2026_06_14_144734_create_integrations_table	1
11	2026_06_14_144735_create_events_table	1
12	2026_06_14_160000_create_roles_permissions_tables	1
13	2026_06_14_170000_add_foreign_key_indexes	1
14	2026_06_14_180000_add_suggestion_to_risks_table	1
15	2026_06_14_193517_create_notifications_table	1
16	2026_06_14_193907_create_export_history_table	1
17	2026_06_14_210201_create_settings_table	1
18	2026_06_14_220000_add_manage_risks_permission	1
19	2026_06_15_001220_create_slow_queries_table	1
20	2026_06_15_103227_add_search_indexes	1
21	2026_06_15_120533_create_personal_access_tokens_table	1
22	2026_06_24_083000_add_created_by_to_integrations_table	1
23	2026_08_04_143000_change_integrations_config_to_text	1
24	2026_10_17_080000_drop_slow_queries_boolean_indexes	1
25	2026_10_24_090000_add_sort_order_to_tasks_table	1
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at) FROM stdin;
4105087c-9e7c-4d91-a45a-ccd0866dc902	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"date":"2026-10-31","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 0 \\u4efb\\u52a1 \\u00b7 0 \\u98ce\\u9669 (0 \\u4e25\\u91cd)"}	\N	2026-10-31 08:30:00	2026-10-31 08:30:00
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, name, slug, description, "group", created_at, updated_at) FROM stdin;
1	管理风险	manage_risks	确认、忽略风险并管理风险建议	risks	2026-10-31 07:13:54	2026-10-31 07:13:54
2	查看仪表板	view_dashboard	\N	dashboard	2026-10-31 07:14:01	2026-10-31 07:14:01
3	查看看板	view_board	\N	board	2026-10-31 07:14:01	2026-10-31 07:14:01
4	查看项目	view_projects	\N	projects	2026-10-31 07:14:01	2026-10-31 07:14:01
5	管理项目	manage_projects	\N	projects	2026-10-31 07:14:01	2026-10-31 07:14:01
6	查看资源负载	view_resources	\N	resources	2026-10-31 07:14:01	2026-10-31 07:14:01
7	查看指标	view_metrics	\N	metrics	2026-10-31 07:14:01	2026-10-31 07:14:01
8	管理指标	manage_metrics	\N	metrics	2026-10-31 07:14:01	2026-10-31 07:14:01
9	查看报表	view_reports	\N	reports	2026-10-31 07:14:01	2026-10-31 07:14:01
10	管理集成	manage_integrations	\N	integrations	2026-10-31 07:14:01	2026-10-31 07:14:01
11	管理系统设置	manage_settings	\N	settings	2026-10-31 07:14:01	2026-10-31 07:14:01
12	管理角色权限	manage_roles	\N	roles	2026-10-31 07:14:01	2026-10-31 07:14:01
13	查看风险中心	view_risks	\N	risks	2026-10-31 07:14:01	2026-10-31 07:14:01
14	查看慢查询监控	view_monitoring	\N	monitoring	2026-10-31 07:14:01	2026-10-31 07:14:01
\.


--
-- Data for Name: risks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.risks (id, project_id, severity, rule_key, title, detail, evidence, status, first_seen_at, last_seen_at, acknowledged_at, closed_at, occurrence_count, meta, created_at, updated_at, suggestion, suggestion_data, suggestion_applied_at) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, name, slug, description, is_system, created_at, updated_at) FROM stdin;
1	管理员	admin	拥有系统所有权限	t	2026-10-31 07:14:01	2026-10-31 07:14:01
2	项目经理	manager	可查看所有数据并管理项目、指标和集成	t	2026-10-31 07:14:01	2026-10-31 07:14:01
3	查看者	viewer	只读查看仪表板、项目、资源和报表	t	2026-10-31 07:14:01	2026-10-31 07:14:01
\.


--
-- Data for Name: role_permission; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_permission (role_id, permission_id) FROM stdin;
1	1
1	2
1	3
1	4
1	5
1	6
1	7
1	8
1	9
1	10
1	11
1	12
1	13
1	14
2	1
2	2
2	3
2	4
2	5
2	6
2	7
2	8
2	9
2	10
2	13
2	14
3	2
3	3
3	4
3	6
3	7
3	9
3	13
3	14
\.


--
-- Data for Name: role_user; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_user (role_id, user_id) FROM stdin;
1	1
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.settings (id, key, value, "group", created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: slow_queries; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.slow_queries (id, query, bindings, "time", connection_name, caller, request_uri, request_method, user_id, is_slow, is_critical, captured_at) FROM stdin;
1	select * from "cache" where "key" in (?)	["laravel_cache_illuminate:queue:paused:database:default"]	4618.37	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 07:40:26
\.


--
-- Name: assignments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.assignments_id_seq', 1, false);


--
-- Name: events_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.events_id_seq', 1, false);


--
-- Name: export_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.export_history_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: integrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.integrations_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: metric_snapshots_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.metric_snapshots_id_seq', 1, false);


--
-- Name: metrics_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.metrics_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 25, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 14, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: projects_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.projects_id_seq', 9, true);


--
-- Name: risks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.risks_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 3, true);


--
-- Name: settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.settings_id_seq', 1, false);


--
-- Name: slow_queries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.slow_queries_id_seq', 1, true);


--
-- Name: tasks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tasks_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 1, true);


--
-- PostgreSQL database dump complete
--
