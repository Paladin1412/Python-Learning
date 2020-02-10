from normal_modules.normal import NormalTester

#taskid:
#签到：216，sid=10662
#投币：217
class SpringPlan(NormalTester):
    def __init__(self, sess_cookies: dict):
        super(SpringPlan, self).__init__(sess_cookies)

    def sp_get_url(self):
        print(self.get_url_json(self.url))

    def sp_post_url(self):
        print(self.post_url_json(url=self.url, data=self.data))

    def t57(self, sid):
        # 57.获取任务列表
        self.url = "http://api.bilibili.com/x/activity/task/list?sid=" + str(sid)
        self.sp_get_url()

    def t60(self, csrf, taskId):
        # 60.领取任务奖励（投币）
        self.url = "http://api.bilibili.com/x/activity/task/award"
        self.data = {
            "csrf": csrf,
            "task_id": taskId
        }
        self.sp_post_url()

    def t72(self, sid, taskId, token=None):
        # 72. 活动特殊任务完成任务接口
        self.url = "http://api.bilibili.com/x/activity/single/task/do"
        self.data = {
            "sid": sid,
            "task_id": taskId,
            "token": token  # Not Necessary
        }
        self.sp_post_url()

    def t93(self, sid, tid):
        # 93.精选榜单（运营数据源）
        self.url = "http://api.bilibili.com/x/activity/single/arc/list?sid=" + str(sid) + "&tid=" + str(tid)
        self.sp_get_url()

    def t96(self, sid):
        # 96.邀请类型活动/任务下发token（分享前调用）
        self.url = "http://uat-api.bilibili.co/x/activity/likeact/token?sid=" + str(sid)
        self.sp_get_url()

    def t97(self, sid, token):
        # 97.邀请好友注册后聚合接口
        self.data = {
            "sid": sid,
            "token": token
        }
        self.url = "http://api.bilibili.com/x/activity/single/task/token/do"
        self.sp_post_url()

    def t98(self, sid):
        # 98.集卡活动集齐套数接口
        self.url = "http://api.bilibili.com/x/activity/single/card/num?sid=" + str(sid)
        self.sp_get_url()

    def t100(self, sid, token):
        # 100.邀请好友注册前检查任务情况
        self.url = "http://api.bilibili.com/x/activity/task/check?sid=" + str(sid) + "&token=" + token
        self.sp_post_url()

    def t_get_card(self,csrf):
        self.url="https://uat-api.bilibili.com/x/activity/lottery/do"
        self.data={
            "sid":"2d82d70e-25f9-11ea-bfa0-246e9693a590",
            "type":1,
            "csrf":csrf
        }
        self.sp_post_url()

    def extra_test(self):
        self.get_url("http://www.bilibili.com")
        self.url="http://api.bilibili.com/x/activity/likes/add/other"
        self.data={
            "sid":10691,
            "csrf":"23d391b78f1f16e7bad365298e3139b0"
        }
        self.sp_post_url()
if __name__ == "__main__":
    cook = {
        "buvid3":"6099ADA3-B383-435A-89CC-886533BE9E0F6103infoc",
        "_uuid":"C0AEFB9C-4BD8-7198-F241-0B0FBDA3C4F876004infoc",
        "fts":"1578020592",
        "LIVE_BUVID":"AUTO7415780206028841",
        "sid":"kiqkxs9h",
        "bp_t_offset_13090258":"341349861655486121",
        "CURRENT_FNVAL":"16",
        "stardustvideo":"1",
        "rpdid":"|(RY|lkJk|R0J'ul~)J~Ru|R",
        "laboratory":"1-1",
        "bp_t_offset_15555180":"341947824181624853",
        "innersign":"1",
        "bp_t_offset_2232":"344542233538609933",
        "im_notify_type_27515430":"0",
        "DedeUserID":"1461342",
        "DedeUserID__ckMd5":"ca9659b1002c264d",
        "SESSDATA":"fecabdea%2C1581663167%2C37188411",
        "bili_jct":"23d391b78f1f16e7bad365298e3139b0",
        "stardustpgcv":"0606",
        "CURRENT_QUALITY":"80"
    }
    sids=[10662,10664]
    sp = SpringPlan(sess_cookies=cook)

    # sp.t57(10691)
    # sp.extra_test()
    # [sp.t57(i)  for i in sids]
    # sp.t93(62,372)
    # sp.t98("2d82d70e-25f9-11ea-bfa0-246e9693a590")
    #
    # sp.t_get_card("a03f389e0d0f0427ccddb6a3c6ee4f7b")
    # sp.t60(csrf="2e82d292d090f396494ea575692cf8d2",taskId=217)
    # sp.t60()
    # sp.t_get_card("2e82d292d090f396494ea575692cf8d2")
