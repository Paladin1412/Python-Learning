import redis

rc = redis.Redis(host="172.22.33.30",port="7304")
a = rc.get("a")
print(a.decode("utf-8"))