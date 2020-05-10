import subprocess


class MonkeyTester:
    def __init__(self, times):
        self.times = times

    @classmethod
    def monkey_test(cls):
        ret = subprocess.Popen("adb shell monkey -p com.xiaodianshi.tv.yst --throttle 50 -vvv 500", shell=True,
                               stdout=subprocess.PIPE)
        a = ret.stdout.read().decode('utf-8')
        if "Monkey finished" not in a:
            print(a)
            raise Exception("monkey test failed")

    @classmethod
    def back_to_index(cls):
        [subprocess.call("adb shell input keyevent 4", shell=True) for i in range(3)]

    def main(self):
        for i in range(self.times):
            print("This is %d testing.." % (i + 1))
            self.monkey_test()
            self.back_to_index()


if __name__ == '__main__':
    mk = MonkeyTester(50)
    mk.main()

