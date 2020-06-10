import json

target = '表情'
with open("raw.json", "r", encoding="utf-8") as fp:
    raw = fp.read()
j = json.loads(raw)
collections = j["collections"]

for collect in collections:
    if target == collect["name"]:
        print("共计长度：", len(collect["order"]))
        reqs = collect["requests"]
        for req in reqs:
            print("req:", req["name"])
            print("url:", req["url"])
            print("method:", req["method"])
            print("params:", len(req["queryParams"]))
            if isinstance(req["data"],list):
                for data in req['data']:
                    print(data)
            else:
                print("data:", req['data'])
            print("-" * 100)
