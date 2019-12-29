import redis

rc = redis.Redis(host="192.168.11.31",port="30002")
a = rc.get("a")
print(a.decode("utf-8"))