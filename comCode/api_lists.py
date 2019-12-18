def choose_api(domain, apiPart):
    api_item = {
        "modpage": {"apipart": "/x/tv/modpage", "method": "get",
                    "param": [{"name": "page_id", "need": "y", "type": "int",
                               "note": "页面id，0=主页，1=番剧，2=电影，3=纪录片，4=国创，5=电视剧，6=音乐，7=游戏，8=科技，9=生活，10=时尚"},
                              {"name": "build", "need": "y", "type": "int",
                               "note": "版本过滤逻辑使用，<=1011时只出pgc模块和pgc干预，反之ugc和pgc混排"},
                              {"name": "access_key", "need": "n", "type": "string",
                               "note": "如果页面包含追番模块,且用户已登陆，可以获取追番数据"}]},
        "modpage_v2": "/x/tv/modpage_v2",
        "homepage": "/x/tv/homepage",
        "zonepage": "/x/tv/zonepage",
        "zone_index": "/x/tv/zone_index",
        "region": "/x/tv/region",
        "pgc": "/x/tv/index/pgc",
        "ugc": "/x/tv/index/ugc",
        "upper": "/x/tv/index/upper",
        "loadep": "/x/tv/loadep",
        "load_video": "/x/tv/ugc/load_video",
        "history": "/x/tv/history",
        "transcode": "/x/tv/audit/transcode"
    }
    if apiPart in api_item.keys():
        return api_item[apiPart]
    else:
        return None
