import redis
from spiderProject.spider12306.dbProcess import DbProcess

rc = redis.Redis(host="192.168.11.31", port="30002")
a = rc.get("上海")
print(a.decode("utf-8"))