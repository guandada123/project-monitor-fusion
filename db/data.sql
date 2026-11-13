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
3	StockInsight	stockinsight	ops_snapshot	StockInsight	\N	active	独立股票分析(桌面端)	2026-10-31	\N	{"code": {"todos": 0, "src_loc": 34749, "test_loc": 31535, "src_files": 138, "total_loc": 66284, "truncated": false, "test_files": 91}, "head": "7c463b1", "path": "/Volumes/ZHITAI/WorkBuddy/StockInsight", "repo": "StockInsight", "role": "独立股票分析(桌面端)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-11-13T23:52:41+08:00", "project": "StockInsight", "priority": "P2", "ci_status": "green", "ci_scanned": true, "commits_7d": 17, "ci_main_red": false, "dirty_files": 1, "path_exists": true, "head_subject": "chore(auto): 自动产物例行提交（2026-11-13）", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-11-13T15:52:48Z", "dirty_oldest_h": 3.1, "ci_red_branches": 0, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
7	wechat-download-api	wechat-download-api	ops_snapshot	wechat-download-api	\N	active	公众号抓取三件套(MCP入口+底层下载)	2026-10-31	\N	{"code": null, "head": null, "path": "~/WorkBuddy/wechat-download-api", "repo": "wechat-download-api", "role": "公众号抓取三件套(MCP入口+底层下载)", "vibe": null, "branch": null, "is_git": false, "head_ts": null, "project": "wechat_api+wechat-download-api", "priority": "P1", "ci_status": "green", "ci_scanned": true, "commits_7d": null, "ci_main_red": false, "dirty_files": null, "path_exists": false, "head_subject": null, "collect_error": "not_a_git_repo", "ledger_status": "retired", "ci_last_run_at": "2026-10-24T03:46:53Z", "dirty_oldest_h": null, "ci_red_branches": 0, "same_declaration": true}	["P1"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
1	Claw	claw	ops_snapshot	Claw	\N	active	A股投顾自动化核心	2026-10-31	\N	{"code": {"todos": 76, "src_loc": 107325, "test_loc": 33726, "src_files": 407, "total_loc": 141051, "truncated": false, "test_files": 194}, "head": "7671154", "path": "~/WorkBuddy/Claw", "repo": "Claw", "role": "A股投顾自动化核心", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-11-14T02:55:16+08:00", "project": "Claw", "priority": "P0", "ci_status": "green", "ci_scanned": true, "commits_7d": 109, "ci_main_red": false, "dirty_files": 80, "path_exists": true, "head_subject": "feat(autopilot): 轮次有效比统计 + 错题本登记 + RUNBOOK 脚本优先闸门", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-11-13T18:07:25Z", "dirty_oldest_h": 315.6, "ci_red_branches": 0, "same_declaration": false}	["P0"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
9	ai-autonomy-team	ai-autonomy-team	ops_snapshot	ai-autonomy-team	\N	active	多角色AI评审的过程约束与验收工具集（零第三方依赖·纯标准库）	2026-10-31	\N	{"code": {"todos": 16, "src_loc": 36281, "test_loc": 3767, "src_files": 85, "total_loc": 40048, "truncated": false, "test_files": 5}, "head": "777955d", "path": "/Volumes/ZHITAI/WorkBuddy/ai-autonomy-team", "repo": "ai-autonomy-team", "role": "多角色AI评审的过程约束与验收工具集（零第三方依赖·纯标准库）", "vibe": {"step": 3, "stage": "MVP 定义", "deadline": "2026-11-13", "mvp_done": 4, "available": true, "mvp_total": 4, "milestones": 7, "step_source": "scan_inferred", "total_steps": 14, "last_activity": "2026-10-31T23:20:00+08:00"}, "branch": "main", "is_git": true, "head_ts": "2026-11-10T18:49:41+08:00", "project": "ai-autonomy-team", "priority": "P2", "ci_status": "none", "ci_scanned": false, "commits_7d": 2, "ci_main_red": null, "dirty_files": 0, "path_exists": true, "head_subject": "fix(ci): 修 A 文档一致性与 H 真源快照，恢复 selftest 8/8", "collect_error": null, "ledger_status": "active", "ci_last_run_at": null, "dirty_oldest_h": null, "ci_red_branches": null, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
4	marvis_bridge	marvis-bridge	ops_snapshot	marvis_bridge	\N	active	Shell+Python桥接调度	2026-10-31	\N	{"code": {"todos": 3, "src_loc": 4778, "test_loc": 1117, "src_files": 24, "total_loc": 5895, "truncated": false, "test_files": 3}, "head": "3b77e74", "path": "~/WorkBuddy/marvis_bridge", "repo": "marvis_bridge", "role": "Shell+Python桥接调度", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-24T08:46:22+08:00", "project": "marvis_bridge", "priority": "P2", "ci_status": "none", "ci_scanned": false, "commits_7d": 0, "ci_main_red": null, "dirty_files": 0, "path_exists": true, "head_subject": "chore(archive): 补入下线前未入库的任务结果与完成记录（32 个）", "collect_error": null, "ledger_status": "retired", "ci_last_run_at": null, "dirty_oldest_h": null, "ci_red_branches": null, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
5	project-monitor-fusion	project-monitor-fusion	ops_snapshot	project-monitor-fusion	\N	active	Laravel监控面板(已融合ops-center, :8088)	2026-10-31	\N	{"code": {"todos": 2, "src_loc": 36018, "test_loc": 480, "src_files": 235, "total_loc": 36498, "truncated": false, "test_files": 3}, "head": "29cb1d0", "path": "~/WorkBuddy/project-monitor-fusion", "repo": "project-monitor-fusion", "role": "Laravel监控面板(已融合ops-center, :8088)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-11-14T01:23:19+08:00", "project": "project-monitor-fusion", "priority": "P2", "ci_status": "green", "ci_scanned": true, "commits_7d": 12, "ci_main_red": false, "dirty_files": 0, "path_exists": true, "head_subject": "deps(composer): bump livewire/flux from 2.14.1 to 2.21.0 (#29)", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-11-13T17:24:47Z", "dirty_oldest_h": null, "ci_red_branches": 0, "same_declaration": false}	["P2"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
2	QuantTradingSystem	quanttradingsystem	ops_snapshot	QuantTradingSystem	\N	active	Docker 13容器量化系统	2026-10-31	\N	{"code": {"todos": 3, "src_loc": 45789, "test_loc": 43147, "src_files": 219, "total_loc": 88936, "truncated": false, "test_files": 97}, "head": "7fc5512", "path": "~/WorkBuddy/QuantTradingSystem", "repo": "QuantTradingSystem", "role": "Docker 13容器量化系统", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-11-10T11:24:19+08:00", "project": "QuantTradingSystem", "priority": "P1", "ci_status": "green", "ci_scanned": true, "commits_7d": 10, "ci_main_red": false, "dirty_files": 1, "path_exists": true, "head_subject": "fix(test): 修契约恒真断言 + 移除失效的全局覆盖率门禁", "collect_error": null, "ledger_status": "active", "ci_last_run_at": "2026-11-10T08:54:06Z", "dirty_oldest_h": 27.9, "ci_red_branches": 0, "same_declaration": false}	["P1"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
6	wechat_api	wechat-api	ops_snapshot	wechat_api	\N	active	公众号抓取三件套(MCP入口+底层下载)	2026-10-31	\N	{"code": {"todos": 1, "src_loc": 9488, "test_loc": 0, "src_files": 39, "total_loc": 9488, "truncated": false, "test_files": 0}, "head": "2173596", "path": "~/WorkBuddy/wechat_api", "repo": "wechat_api", "role": "公众号抓取三件套(MCP入口+底层下载)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-10-24T11:45:28+08:00", "project": "wechat_api+wechat-download-api", "priority": "P1", "ci_status": "none", "ci_scanned": false, "commits_7d": 0, "ci_main_red": null, "dirty_files": 1, "path_exists": true, "head_subject": "fix(security): 端口绑定由 0.0.0.0 收紧到 127.0.0.1", "collect_error": null, "ledger_status": "active", "ci_last_run_at": null, "dirty_oldest_h": 331.8, "ci_red_branches": null, "same_declaration": true}	["P1"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
8	engineering-audit-kit	engineering-audit-kit	ops_snapshot	engineering-audit-kit	\N	active	审计工具包(休眠)	2026-10-31	\N	{"code": {"todos": 0, "src_loc": 807, "test_loc": 0, "src_files": 4, "total_loc": 807, "truncated": false, "test_files": 0}, "head": "6fa8cc8", "path": "~/WorkBuddy/engineering-audit-kit", "repo": "engineering-audit-kit", "role": "审计工具包(休眠)", "vibe": null, "branch": "main", "is_git": true, "head_ts": "2026-11-02T10:32:24+08:00", "project": "engineering-audit-kit", "priority": "P3", "ci_status": "none", "ci_scanned": false, "commits_7d": 0, "ci_main_red": null, "dirty_files": 0, "path_exists": true, "head_subject": "ci: 硬编码路径门改为真阻断，并给发 tag 补一条必做步骤", "collect_error": null, "ledger_status": "dormant", "ci_last_run_at": null, "dirty_oldest_h": null, "ci_red_branches": null, "same_declaration": false}	["P3"]	{"snapshot_generated_at": "2026-11-14T02:57:15+08:00"}	2026-10-31 15:14:01	2026-11-14 02:59:56	\N
\.


--
-- Data for Name: tasks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tasks (id, project_id, external_id, title, description, assignee_id, status, priority, estimate, spent, start_at, due_at, completed_at, tags, meta, created_at, updated_at, deleted_at, sort_order, creator_id) FROM stdin;
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
27	2026_11_05_114500_add_creator_id_to_tasks_table	2
28	2026_11_05_120000_add_creator_id_index_and_foreign_key_to_tasks_table	3
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notifications (id, type, notifiable_type, notifiable_id, data, read_at, created_at, updated_at) FROM stdin;
4105087c-9e7c-4d91-a45a-ccd0866dc902	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"date":"2026-10-31","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 0 \\u4efb\\u52a1 \\u00b7 0 \\u98ce\\u9669 (0 \\u4e25\\u91cd)"}	\N	2026-10-31 16:30:00	2026-10-31 16:30:00
5e6be598-c56a-4891-922c-6ca326a14868	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":363380,"commits_7d":304,"ci_red_projects":0,"date":"2026-10-31","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 36.3 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 304 \\u6b21\\u63d0\\u4ea4"}	\N	2026-10-31 20:06:59	2026-10-31 20:06:59
475e3313-ec8d-4b86-ad1f-1f968e4f94c4	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":363708,"commits_7d":316,"ci_red_projects":0,"date":"2026-10-31","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 36.4 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 316 \\u6b21\\u63d0\\u4ea4"}	\N	2026-10-31 22:13:52	2026-10-31 22:13:52
b8b3ac8d-dfb5-48cf-9b07-1c227754fcce	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":364953,"commits_7d":334,"ci_red_projects":1,"date":"2026-11-01","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 36.5 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 334 \\u6b21\\u63d0\\u4ea4 \\u00b7 1 \\u4e2a\\u4e3b\\u5206\\u652f CI \\u7ea2"}	\N	2026-11-01 08:00:10	2026-11-01 08:00:10
68b18f08-23e0-48bb-aef7-a994ea89df62	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":364953,"commits_7d":334,"ci_red_projects":1,"date":"2026-11-01","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 36.5 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 334 \\u6b21\\u63d0\\u4ea4 \\u00b7 1 \\u4e2a\\u4e3b\\u5206\\u652f CI \\u7ea2"}	\N	2026-11-01 08:30:00	2026-11-01 08:30:00
1ceec5c3-e269-4800-83dc-e11ee836e89d	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":367851,"commits_7d":354,"ci_red_projects":0,"date":"2026-11-02","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 36.8 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 354 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-02 08:30:00	2026-11-02 08:30:00
f2d3c525-4d85-489b-98c9-bdb85764b2ae	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":375466,"commits_7d":333,"ci_red_projects":0,"date":"2026-11-03","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.5 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 333 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-03 08:30:00	2026-11-03 08:30:00
98f428e0-b229-4816-9221-35fc1afdbb62	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":376807,"commits_7d":336,"ci_red_projects":0,"date":"2026-11-04","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.7 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 336 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-04 08:30:00	2026-11-04 08:30:00
18dfbad4-c0b6-4d65-8f1a-34f1496acf40	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":376935,"commits_7d":324,"ci_red_projects":0,"date":"2026-11-05","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.7 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 324 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-05 08:30:00	2026-11-05 08:30:00
0b54ce02-3869-44e6-b353-b2ecb43d55f8	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":377810,"commits_7d":291,"ci_red_projects":0,"date":"2026-11-06","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.8 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 291 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-06 08:30:00	2026-11-06 08:30:00
548c9f31-a37e-40fa-bc0e-ceba53afdf0c	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":378671,"commits_7d":281,"ci_red_projects":0,"date":"2026-11-07","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.9 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 281 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-07 08:30:00	2026-11-07 08:30:00
be131a53-11be-4873-9d9a-17ac868de66d	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":378899,"commits_7d":190,"ci_red_projects":1,"date":"2026-11-08","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.9 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 190 \\u6b21\\u63d0\\u4ea4 \\u00b7 1 \\u4e2a\\u4e3b\\u5206\\u652f CI \\u7ea2"}	\N	2026-11-08 08:30:00	2026-11-08 08:30:00
c0627176-68e2-4cec-b67c-828c985f77a3	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":379004,"commits_7d":131,"ci_red_projects":1,"date":"2026-11-09","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.9 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 131 \\u6b21\\u63d0\\u4ea4 \\u00b7 1 \\u4e2a\\u4e3b\\u5206\\u652f CI \\u7ea2"}	\N	2026-11-09 08:30:00	2026-11-09 08:30:00
36cb12ed-9086-42be-a0c4-7609e865484d	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":379053,"commits_7d":110,"ci_red_projects":0,"date":"2026-11-10","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 37.9 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 110 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-10 08:30:00	2026-11-10 08:30:00
4fbe9efc-92e1-4ab3-a0b2-bf05cc763d21	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":379959,"commits_7d":112,"ci_red_projects":0,"date":"2026-11-11","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 38.0 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 112 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-11 08:30:00	2026-11-11 08:30:00
366306c7-dae4-4bb4-8ccb-d7a350bab64f	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":380814,"commits_7d":119,"ci_red_projects":0,"date":"2026-11-12","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 38.1 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 119 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-12 08:31:00	2026-11-12 08:31:00
99ece3fd-31c2-40c3-82c9-ad0cbf5ea09d	App\\Notifications\\DailyReportNotification	App\\Models\\User	1	{"type":"daily_report","active_projects":9,"total_tasks":0,"open_risks":0,"critical_risks":0,"total_loc":385372,"commits_7d":109,"ci_red_projects":0,"date":"2026-11-13","url":"\\/","icon":"calendar","message":"\\u65e5\\u62a5 9 \\u9879\\u76ee \\u00b7 38.5 \\u4e07\\u884c \\u00b7 \\u8fd1 7 \\u5929 109 \\u6b21\\u63d0\\u4ea4"}	\N	2026-11-13 08:30:00	2026-11-13 08:30:00
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
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.settings (id, key, value, "group", created_at, updated_at) FROM stdin;
1	feishu_daily_report	true	notification	2026-11-01 21:03:58	2026-11-01 21:03:58
2	feishu_risk_alert	true	notification	2026-11-01 21:03:58	2026-11-01 21:03:58
4	feishu_webhook_url	~enc~eyJpdiI6Imoya1JKOUpaUTk4T2VieFhSTzQ2eXc9PSIsInZhbHVlIjoiVE5NakhiVkE3emZoWVZmMUNMZDIxL1JSd1FWclJSdmpwNm5sbHpBQUUybjNlWTkxOUR6RTdzVkZuNDEwc2F1UVlVQXZJdkFMdnRhTHc3ZlNQelh5djRMdmpGN0krQWFVL0NmMFZFLzMrUlhEWnFQbmozaFFjS3M0Qkppa3lmK2giLCJtYWMiOiI4NWVkNzdkMGE2MDE4YWMxMThjMWFkM2U2Y2NiZDVmMzQyOGRkYzAzZTFhZWQ3Y2U3ODA2ZmE5NDJjYTE2YWZjIiwidGFnIjoiIn0=	notification	2026-11-01 21:03:58	2026-11-01 21:03:58
5	feishu_partition_tag	[项目监控]	notification	2026-11-01 21:03:58	2026-11-01 21:03:58
6	feishu_bot_name	Project Monitor	notification	2026-11-01 21:03:58	2026-11-01 21:03:58
3	feishu_daily_time	08:30	notification	2026-11-01 21:03:58	2026-11-01 21:37:02
\.


--
-- Data for Name: slow_queries; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.slow_queries (id, query, bindings, "time", connection_name, caller, request_uri, request_method, user_id, is_slow, is_critical, captured_at) FROM stdin;
1	select * from "cache" where "key" in (?)	["laravel_cache_illuminate:queue:paused:database:default"]	4618.37	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 07:40:26
2	select * from "cache" where "key" in (?)	["laravel_cache_illuminate:queue:restart"]	4123.67	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 11:05:46
3	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793444871","1793444781"]	4266.72	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 11:07:56
4	delete from "cache"	[]	4293.10	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 11:43:46
5	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793457582","1793457492"]	3987.32	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 22:39:46
6	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793459982","1793459892"]	4278.64	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-10-31 23:19:46
7	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793477062","1793476972"]	4279.58	pgsql	/var/www/app/Services/SlowQueryMonitor.php:49	/	GET	\N	t	t	2026-11-01 04:04:26
8	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793498395","1793498305"]	949.03	pgsql	/var/www/app/Services/SlowQueryMonitor.php:50	/	GET	\N	t	f	2026-11-01 09:59:56
9	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793604164","1793604074"]	2208.56	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-02 15:22:47
10	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793608764","1793608674"]	2210.76	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-02 16:39:27
11	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793610009","1793609919"]	2022.25	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-02 17:00:11
12	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1793677649","1793677559"]	510.40	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-03 11:47:29
13	update "projects" set "meta" = ?, "config" = ?, "updated_at" = ? where "id" = ?	["{\\"project\\":\\"Claw\\",\\"repo\\":\\"Claw\\",\\"role\\":\\"A\\\\u80a1\\\\u6295\\\\u987e\\\\u81ea\\\\u52a8\\\\u5316\\\\u6838\\\\u5fc3\\",\\"priority\\":\\"P0\\",\\"same_declaration\\":false,\\"path\\":\\"~\\\\\\/WorkBuddy\\\\\\/Claw\\",\\"path_exists\\":true,\\"is_git\\":true,\\"branch\\":\\"main\\",\\"head\\":\\"acfc63b\\",\\"head_ts\\":\\"2026-11-05T10:57:40+08:00\\",\\"head_subject\\":\\"docs(gate): \\\\u00a74.5.1 \\\\u8865 TCC fallback\\\\u2014\\\\u2014\\\\u540e\\\\u53f0\\\\u6388\\\\u6743 python \\\\u5df2\\\\u5b9e\\\\u6d4b\\\\u53ef\\\\u7528\\",\\"dirty_files\\":12,\\"dirty_oldest_h\\":107.8,\\"commits_7d\\":167,\\"ci_status\\":\\"green\\",\\"ci_scanned\\":true,\\"ci_red_branches\\":0,\\"ci_main_red\\":false,\\"ci_last_run_at\\":\\"2026-11-05T02:35:50Z\\",\\"ledger_status\\":\\"active\\",\\"vibe\\":null,\\"code\\":{\\"src_files\\":379,\\"src_loc\\":97799,\\"test_files\\":185,\\"test_loc\\":31871,\\"total_loc\\":129670,\\"todos\\":31,\\"truncated\\":false},\\"collect_error\\":null}","{\\"snapshot_generated_at\\":\\"2026-11-05T11:14:09+08:00\\"}","2026-11-05 11:31:00","1"]	605.10	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-05 11:31:00
14	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794032115","1794032025"]	2818.71	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-07 14:15:18
15	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794063336","1794063246"]	1822.64	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-07 22:55:38
16	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794126854","1794126764"]	4213.65	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-08 16:34:19
17	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794144197","1794144107"]	2342.69	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-08 21:23:19
18	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794152267","1794152177"]	2151.64	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-08 23:37:49
19	select * from "projects" where "status" in (?, ?) and "projects"."deleted_at" is null	["active","pending"]	584.46	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-11 14:30:00
20	select count(*) as aggregate from "tasks" where "project_id" = ? and "status" != ? and "due_at" is not null and "due_at" < ? and "tasks"."deleted_at" is null	["7","done","2026-11-11"]	617.41	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-11 15:30:00
21	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794384200","1794384110"]	676.02	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-11 16:03:21
22	select * from "projects" where "source" = ? and "external_id" = ? and "projects"."deleted_at" is null limit 1	["ops_snapshot","engineering-audit-kit"]	644.89	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-11 17:01:01
23	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794390180","1794390090"]	655.58	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-11 17:43:01
24	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794399399","1794399309"]	1498.77	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-11 20:16:41
25	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794399739","1794399649"]	1499.13	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-11 20:22:21
26	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794435458","1794435368"]	2381.24	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-12 06:17:40
27	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794455586","1794455496"]	4151.80	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-12 11:53:11
28	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794461326","1794461236"]	4248.12	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-12 13:28:51
29	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794502027","1794501937"]	4417.63	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-13 00:47:11
30	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794502547","1794502457"]	4250.43	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-13 00:55:51
31	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794524550","1794524460"]	541.42	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	f	2026-11-13 07:02:31
32	select * from "jobs" where "queue" = ? and (("reserved_at" is null and "available_at" <= ?) or ("reserved_at" <= ?)) order by "id" asc limit 1 FOR UPDATE SKIP LOCKED	["default","1794582047","1794581957"]	4329.86	pgsql	/var/www/app/Services/SlowQueryMonitor.php:51	/	GET	\N	t	t	2026-11-13 23:00:51
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

SELECT pg_catalog.setval('public.migrations_id_seq', 28, true);


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

SELECT pg_catalog.setval('public.settings_id_seq', 6, true);


--
-- Name: slow_queries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.slow_queries_id_seq', 32, true);


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
