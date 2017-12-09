import argparse
import sys
import hashlib
import hmac

def main(argv):
    parser = argparse.ArgumentParser()
    parser.add_argument('-k','--key',metavar='',help='',required=True)
    parser.add_argument('-d','--date',metavar='',help='',required=True)
    parser.add_argument('-r','--region',metavar='',help='',required=True)
    parser.add_argument('-s','--service',metavar='',help='',required=True)

    argv = parser.parse_args()
    print getSignatureKey(argv.key, argv.date, argv.region, argv.service)

def sign(key, msg):
    return hmac.new(key, msg.encode("utf-8"), hashlib.sha256).digest()

def getSignatureKey(key, dateStamp, regionName, serviceName):
    kDate = sign(("AWS4" + key).encode("utf-8"), dateStamp)
    kRegion = sign(kDate, regionName)
    kService = sign(kRegion, serviceName)
    kSigning = sign(kService, "aws4_request")
    return kSigning

if __name__ == "__main__":
    main(sys.argv[1:])
