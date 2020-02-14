from rediscluster import RedisCluster
import json


class RedisCli:
    def _conn_redis(self, startup_nodes):
        self.my_redis = RedisCluster(startup_nodes=startup_nodes, decode_responses=True, skip_full_coverage_check=True)

    @classmethod
    def handle_json_object(cls, rep):
        try:
            return json.loads(rep)
        except Exception as e:
            print("error:", e)

    def get_code_by_phone_num(self, phone_num):
        startup_nodes = [{"host": "172.22.33.30", "port": "7304"}]
        self._conn_redis(startup_nodes)
        return self.handle_json_object(self.my_redis.get("sms_rp_lg_1_{}".format(phone_num)))

    def get_code_by_captcha_key(self, captcha_key):
        startup_nodes = [{"host": "172.22.33.30", "port": "7374"}]
        self._conn_redis(startup_nodes)
        return self.handle_json_object(self.my_redis.get("sms_{}".format(captcha_key)))


if __name__ == '__main__':
    rc = RedisCli()
    while True:
        cp_key = input("captcha_key:\n")
        if cp_key == 'q':
            break
        ret = rc.get_code_by_captcha_key(cp_key)
        print(ret, end='\n')
